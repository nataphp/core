<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Email\Mailer\Transport;

use InvalidArgumentException;
use Nata\Email\Mailer\Message;
use Nata\Email\Mailer\Transport;
use Nata\I18n\Time;

/**
 * Native SMTP transport.
 *
 * Speaks the SMTP protocol directly over a stream socket without depending on
 * PHPMailer (or any third-party library). It handles connection, the EHLO
 * handshake, optional STARTTLS / implicit TLS, AUTH LOGIN / AUTH PLAIN
 * authentication, envelope negotiation and the DATA phase. The RFC 5322 / MIME
 * message (including multipart/alternative, multipart/related for inline images
 * and multipart/mixed for attachments) is assembled in-house from the
 * {@see Message} instance.
 */
class Smtp extends Transport {

/**
 * Protocol line terminator required by SMTP (RFC 5321 §2.3.7).
 *
 * @var string
 */
    const CRLF = "\r\n";

/**
 * Default configuration.
 *
 * - `host` SMTP server hostname or IP.
 * - `port` SMTP server port (25 plain, 465 implicit TLS, 587 STARTTLS).
 * - `secure` Encryption: '' (none/opportunistic STARTTLS), 'tls' (STARTTLS) or 'ssl' (implicit TLS).
 * - `auth` Whether to authenticate using username/password.
 * - `username` SMTP username.
 * - `password` SMTP password.
 * - `timeout` Socket timeout in seconds.
 * - `hostname` Client name announced in EHLO/HELO. Defaults to the machine hostname.
 * - `verifyPeer` Whether to verify the server TLS certificate. Disable only for dev servers.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'host' => 'localhost',
        'port' => 25,
        'secure' => '',
        'auth' => true,
        'username' => null,
        'password' => null,
        'timeout' => 30,
        'hostname' => null,
        'verifyPeer' => true
    ];

/**
 * Active stream socket resource, or null when disconnected.
 *
 * @var resource|null
 */
    protected $_socket = null;

/**
 * Lowercased extension keywords advertised by the server in its EHLO reply.
 * Keyed by keyword (e.g. 'starttls', 'auth') with the raw parameter string as value.
 *
 * @var array
 */
    protected $_serverExtensions = [];

/**
 * Send an email message over SMTP.
 *
 * Runs the full SMTP conversation and, on success, records the transport id
 * (from the message Message-Id) and a synthetic positive SMTP reply. Any
 * protocol or socket failure is captured in {@see Transport::$_errors} and the
 * method returns false.
 *
 * @param Message $message Email message to send.
 * @return bool True when the server accepted the message, false otherwise.
 */
    public function send(Message $message): bool {
        parent::_uniquifyAddresses($message);

        try {
            if (!$this->_connect()) {
                return false;
            }

            $clientHostname = $this->_clientHostname();

            if (!$this->_ehlo($clientHostname)) {
                return false;
            }

            if ($this->_shouldStartTls() && !$this->_startTls($clientHostname)) {
                return false;
            }

            if ($this->config('auth') && !$this->_authenticate()) {
                return false;
            }

            if (!$this->_sendEnvelope($message)) {
                return false;
            }

            $mimeData = $this->_buildMime($message);
            if (!$this->_transmitData($mimeData)) {
                return false;
            }
        } finally {
            $this->_disconnect();
        }

        $this->_id = trim((string)$message->messageId(), '<>');

        return true;
    }

/**
 * Open the socket to the SMTP server and read the greeting banner.
 *
 * Uses an implicit-TLS (ssl://) wrapper when `secure` is 'ssl', otherwise a
 * plain TCP connection that may later be upgraded via STARTTLS.
 *
 * @return bool True on a 220 greeting, false on failure (error recorded).
 * @throws \InvalidArgumentException When no host is configured.
 */
    protected function _connect(): bool {
        $host = $this->config('host');
        if (empty($host)) {
            throw new InvalidArgumentException('Missing SMTP host for SmtpSocket transport.');
        }

        $port = (int)$this->config('port');
        $timeout = (int)$this->config('timeout');
        $scheme = $this->config('secure') === 'ssl' ? 'ssl' : 'tcp';
        $remote = sprintf('%s://%s:%d', $scheme, $host, $port);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => (bool)$this->config('verifyPeer'),
                'verify_peer_name' => (bool)$this->config('verifyPeer'),
                'SNI_enabled' => true,
                'peer_name' => $host
            ]
        ]);

        $errorNumber = 0;
        $errorMessage = '';
        $socket = @stream_socket_client(
            $remote,
            $errorNumber,
            $errorMessage,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            $this->_errors[] = sprintf('SMTP connection to %s failed: %s (%d).', $remote, $errorMessage, $errorNumber);
            return false;
        }

        $this->_socket = $socket;
        stream_set_timeout($this->_socket, $timeout);

        $greeting = $this->_readResponse();

        return $this->_expect($greeting, 220, 'connection greeting');
    }

/**
 * Send the EHLO command and capture the advertised server extensions.
 *
 * Falls back to HELO when the server does not understand EHLO.
 *
 * @param string $clientHostname Hostname announced to the server.
 * @return bool True when the handshake succeeded, false otherwise.
 */
    protected function _ehlo(string $clientHostname): bool {
        $response = $this->_command('EHLO ' . $clientHostname, [250]);

        if ($response === false) {
            $response = $this->_command('HELO ' . $clientHostname, [250]);
            if ($response === false) {
                return false;
            }
            $this->_serverExtensions = [];
            return true;
        }

        $this->_serverExtensions = [];
        foreach ($response['lines'] as $index => $line) {
            if ($index === 0) {
                // First line is the greeting text, not an extension.
                continue;
            }
            $parts = explode(' ', trim($line), 2);
            $keyword = strtolower($parts[0]);
            $this->_serverExtensions[$keyword] = isset($parts[1]) ? trim($parts[1]) : '';
        }

        return true;
    }

/**
 * Decide whether TLS must be negotiated via STARTTLS.
 *
 * True when `secure` is 'tls', or when `secure` is '' and the server
 * advertises STARTTLS (opportunistic upgrade). Never true for 'ssl' since that
 * connection is already encrypted.
 *
 * @return bool True when STARTTLS should be issued.
 */
    protected function _shouldStartTls(): bool {
        $secure = $this->config('secure');
        if ($secure === 'ssl') {
            return false;
        }
        if ($secure === 'tls') {
            return true;
        }
        return isset($this->_serverExtensions['starttls']);
    }

/**
 * Upgrade the plain connection to TLS using STARTTLS and re-issue EHLO.
 *
 * @param string $clientHostname Hostname announced in the follow-up EHLO.
 * @return bool True when the TLS handshake and re-EHLO succeeded.
 */
    protected function _startTls(string $clientHostname): bool {
        if ($this->_command('STARTTLS', [220]) === false) {
            return false;
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        $enabled = @stream_socket_enable_crypto($this->_socket, true, $cryptoMethod);
        if ($enabled !== true) {
            $this->_errors[] = 'SMTP STARTTLS negotiation failed.';
            return false;
        }

        // The server must be re-greeted over the encrypted channel.
        return $this->_ehlo($clientHostname);
    }

/**
 * Authenticate against the server using the strongest supported mechanism.
 *
 * Prefers AUTH LOGIN, falling back to AUTH PLAIN. When the server advertises
 * neither, AUTH LOGIN is attempted anyway for maximum compatibility.
 *
 * @return bool True on a 235 authentication success, false otherwise.
 */
    protected function _authenticate(): bool {
        $username = (string)$this->config('username');
        $password = (string)$this->config('password');

        $mechanisms = strtoupper($this->_serverExtensions['auth'] ?? '');

        if (str_contains($mechanisms, 'PLAIN') && !str_contains($mechanisms, 'LOGIN')) {
            return $this->_authPlain($username, $password);
        }

        return $this->_authLogin($username, $password);
    }

/**
 * Perform an AUTH LOGIN exchange (base64 username then base64 password).
 *
 * @param string $username SMTP username.
 * @param string $password SMTP password.
 * @return bool True on success, false otherwise.
 */
    protected function _authLogin(string $username, string $password): bool {
        if ($this->_command('AUTH LOGIN', [334]) === false) {
            return false;
        }
        if ($this->_command(base64_encode($username), [334]) === false) {
            return false;
        }
        return $this->_command(base64_encode($password), [235]) !== false;
    }

/**
 * Perform an AUTH PLAIN exchange in a single base64 credential token.
 *
 * @param string $username SMTP username.
 * @param string $password SMTP password.
 * @return bool True on success, false otherwise.
 */
    protected function _authPlain(string $username, string $password): bool {
        $token = base64_encode("\0" . $username . "\0" . $password);
        return $this->_command('AUTH PLAIN ' . $token, [235]) !== false;
    }

/**
 * Negotiate the SMTP envelope: MAIL FROM followed by one RCPT TO per recipient.
 *
 * The envelope sender defaults to the Return-Path address when present,
 * otherwise the From address. Recipients are the union of To, Cc and Bcc.
 *
 * @param Message $message Email message providing the addresses.
 * @return bool True when the sender and at least one recipient were accepted.
 */
    protected function _sendEnvelope(Message $message): bool {
        $sender = $message->returnPath() && !$message->returnPath()->isEmpty()
            ? $message->returnPath()->email()
            : $message->from()->email();

        if ($this->_command('MAIL FROM:<' . $sender . '>', [250]) === false) {
            return false;
        }

        $recipients = $this->_collectRecipients($message);
        if (empty($recipients)) {
            $this->_errors[] = 'SMTP transport has no recipients to deliver to.';
            return false;
        }

        foreach ($recipients as $recipient) {
            // 251 = recipient not local; will forward. Both are acceptable.
            if ($this->_command('RCPT TO:<' . $recipient . '>', [250, 251]) === false) {
                return false;
            }
        }

        return true;
    }

/**
 * Collect the unique set of envelope recipient email addresses.
 *
 * @param Message $message Email message providing To/Cc/Bcc collections.
 * @return array List of unique recipient email addresses.
 */
    protected function _collectRecipients(Message $message): array {
        $recipients = [];

        foreach (['to', 'cc', 'bcc'] as $field) {
            $collection = $message->{$field}();
            if (!$collection || $collection->isEmpty()) {
                continue;
            }
            foreach ($collection as $address) {
                $email = $address->email();
                if ($email) {
                    $recipients[$email] = $email;
                }
            }
        }

        return array_values($recipients);
    }

/**
 * Run the DATA phase: send the DATA command, the dot-stuffed message and the
 * terminating "." then read the final acceptance reply.
 *
 * @param string $mimeData Fully assembled RFC 5322 / MIME message.
 * @return bool True when the server accepted the message body (250).
 */
    protected function _transmitData(string $mimeData): bool {
        if ($this->_command('DATA', [354]) === false) {
            return false;
        }

        $payload = $this->_dotStuff($mimeData);

        if (!$this->_write($payload . self::CRLF . '.' . self::CRLF)) {
            return false;
        }

        $response = $this->_readResponse();
        if (!$this->_expect($response, 250, 'end of DATA')) {
            return false;
        }

        $this->_smtpReply = $response['code'] . ' ' . $response['message'];

        return true;
    }

/**
 * Apply SMTP transparency (dot-stuffing) to the message payload.
 *
 * Any line beginning with a period gets an extra leading period so that it is
 * not mistaken for the end-of-data marker (RFC 5321 §4.5.2). Line endings are
 * normalised to CRLF first.
 *
 * @param string $data Raw message data.
 * @return string Dot-stuffed message data.
 */
    protected function _dotStuff(string $data): string {
        $data = preg_replace('/\r\n|\r|\n/', self::CRLF, $data);
        $lines = explode(self::CRLF, $data);

        foreach ($lines as $index => $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $lines[$index] = '.' . $line;
            }
        }

        return implode(self::CRLF, $lines);
    }

/**
 * Send a command line and validate that the reply carries an expected code.
 *
 * @param string $command Command to send (without trailing CRLF).
 * @param array $expectedCodes Acceptable SMTP status codes.
 * @return array|false Parsed response array on success, false on failure.
 */
    protected function _command(string $command, array $expectedCodes) {
        if (!$this->_write($command . self::CRLF)) {
            return false;
        }

        $response = $this->_readResponse();

        if (!in_array($response['code'], $expectedCodes, true)) {
            $this->_errors[] = sprintf(
                'SMTP command "%s" expected %s, got %d %s.',
                $this->_sanitizeForLog($command),
                implode('/', $expectedCodes),
                $response['code'],
                $response['message']
            );
            return false;
        }

        return $response;
    }

/**
 * Validate a previously read response against a single expected code.
 *
 * @param array $response Parsed response from {@see _readResponse()}.
 * @param int $expectedCode Acceptable SMTP status code.
 * @param string $stage Human-readable stage name for the error message.
 * @return bool True when the code matches, false otherwise (error recorded).
 */
    protected function _expect(array $response, int $expectedCode, string $stage): bool {
        if ($response['code'] !== $expectedCode) {
            $this->_errors[] = sprintf(
                'SMTP %s expected %d, got %d %s.',
                $stage,
                $expectedCode,
                $response['code'],
                $response['message']
            );
            return false;
        }
        return true;
    }

/**
 * Read a (possibly multi-line) SMTP response from the socket.
 *
 * Multi-line replies use "code-" for continuation lines and "code " for the
 * final line (RFC 5321 §4.2.1).
 *
 * @return array{code:int,message:string,lines:array} Parsed response. Code 0 signals a read error.
 */
    protected function _readResponse(): array {
        $lines = [];
        $code = 0;

        do {
            $raw = fgets($this->_socket, 515);
            if ($raw === false) {
                $meta = stream_get_meta_data($this->_socket);
                $reason = !empty($meta['timed_out']) ? 'timed out' : 'connection closed';
                $this->_errors[] = 'SMTP read failed: ' . $reason . '.';
                return ['code' => 0, 'message' => $reason, 'lines' => $lines];
            }

            $code = (int)substr($raw, 0, 3);
            $lines[] = trim(substr($raw, 4));
            $continuation = substr($raw, 3, 1) === '-';
        } while ($continuation);

        return [
            'code' => $code,
            'message' => implode(' ', $lines),
            'lines' => $lines
        ];
    }

/**
 * Write raw bytes to the socket.
 *
 * @param string $data Bytes to send.
 * @return bool True when all bytes were written, false on error (recorded).
 */
    protected function _write(string $data): bool {
        if (!is_resource($this->_socket)) {
            $this->_errors[] = 'SMTP write attempted on a closed socket.';
            return false;
        }

        $written = @fwrite($this->_socket, $data);
        if ($written === false) {
            $this->_errors[] = 'SMTP write failed.';
            return false;
        }

        return true;
    }

/**
 * Politely close the connection (best-effort QUIT) and release the socket.
 *
 * @return void
 */
    protected function _disconnect(): void {
        if (is_resource($this->_socket)) {
            @fwrite($this->_socket, 'QUIT' . self::CRLF);
            @fclose($this->_socket);
        }
        $this->_socket = null;
    }

/**
 * Resolve the client hostname announced during EHLO/HELO.
 *
 * @return string Configured hostname, machine hostname, or 'localhost'.
 */
    protected function _clientHostname(): string {
        $hostname = $this->config('hostname') ?: $this->_hostname;
        if (empty($hostname)) {
            $hostname = gethostname() ?: 'localhost';
        }
        return $hostname;
    }

/**
 * Strip credentials from a command before it is written to the error log.
 *
 * @param string $command Command that may carry a base64 secret.
 * @return string Command safe to log.
 */
    protected function _sanitizeForLog(string $command): string {
        if (str_starts_with($command, 'AUTH ')) {
            return 'AUTH ***';
        }
        // Bare base64 tokens are the LOGIN username/password steps.
        if (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $command)) {
            return '***';
        }
        return $command;
    }

/**
 * Assemble the full RFC 5322 message: top-level headers plus the MIME body.
 *
 * @param Message $message Email message to serialise.
 * @return string Complete message ready for the DATA phase.
 */
    protected function _buildMime(Message $message): string {
        $rootPart = $this->_buildContentPart($message);
        $headers = $this->_buildHeaders($message) + $rootPart['headers'];

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode(self::CRLF, $lines) . self::CRLF . self::CRLF . $rootPart['body'];
    }

/**
 * Build the top-level RFC 5322 headers (envelope-independent).
 *
 * Bcc is intentionally omitted from the header block; it is delivered through
 * the SMTP envelope only. Custom message headers (In-Reply-To, References,
 * Message-Id, …) are merged without overwriting the standard headers.
 *
 * @param Message $message Email message providing addresses and metadata.
 * @return array Ordered map of header name => header value.
 */
    protected function _buildHeaders(Message $message): array {
        if (!$message->messageId()) {
            $message->messageId(true);
        }

        $headers = [];
        $headers['Date'] = (new Time('now', 'Europe/Lisbon'))->format('r');
        $headers['From'] = $this->_formatAddress($message->from());

        if ($message->to() && !$message->to()->isEmpty()) {
            $headers['To'] = $this->_formatAddressList($message->to());
        }

        if ($message->cc() && !$message->cc()->isEmpty()) {
            $headers['Cc'] = $this->_formatAddressList($message->cc());
        }

        if ($message->replyTo() && !$message->replyTo()->isEmpty()) {
            $headers['Reply-To'] = $this->_formatAddressList($message->replyTo());
        }

        if ($message->readReceipt() && !$message->readReceipt()->isEmpty()) {
            $headers['Disposition-Notification-To'] = $message->readReceipt()->first()->email();
        }

        $headers['Subject'] = $this->_encodeHeader((string)$message->subject());
        $headers['X-Priority'] = (string)$message->priority();
        $headers['MIME-Version'] = '1.0';

        foreach ($message->header() as $name => $value) {
            if (!isset($headers[$name])) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

/**
 * Build the root MIME content part for the message body.
 *
 * Produces, depending on the payload:
 * - a single text/plain or text/html part;
 * - multipart/related wrapping the HTML with its inline images;
 * - multipart/alternative pairing the text and HTML variants;
 * - multipart/mixed wrapping the body with regular attachments.
 *
 * @param Message $message Email message providing the body and attachments.
 * @return array{headers:array,body:string} MIME part descriptor.
 */
    protected function _buildContentPart(Message $message): array {
        $textMessage = $message->textMessage();
        $htmlMessage = $message->htmlMessage();

        [$inlineAttachments, $regularAttachments] = $this->_partitionAttachments($message->attachments());

        $htmlEntity = null;
        if ($htmlMessage) {
            $htmlEntity = $this->_textEntity('text/html', $htmlMessage, $message->charset());
            if (!empty($inlineAttachments)) {
                $relatedParts = [$htmlEntity];
                foreach ($inlineAttachments as $attachment) {
                    $relatedParts[] = $this->_attachmentEntity($attachment);
                }
                $htmlEntity = $this->_multipartEntity('related', $relatedParts);
            }
        }

        if ($textMessage && $htmlEntity) {
            $bodyPart = $this->_multipartEntity('alternative', [
                $this->_textEntity('text/plain', $textMessage, $message->charset()),
                $htmlEntity
            ]);
        } elseif ($htmlEntity) {
            $bodyPart = $htmlEntity;
        } else {
            $bodyPart = $this->_textEntity('text/plain', (string)$textMessage, $message->charset());
        }

        if (!empty($regularAttachments)) {
            $mixedParts = [$bodyPart];
            foreach ($regularAttachments as $attachment) {
                $mixedParts[] = $this->_attachmentEntity($attachment);
            }
            $bodyPart = $this->_multipartEntity('mixed', $mixedParts);
        }

        return $bodyPart;
    }

/**
 * Split attachments into inline (referenced by cid) and regular sets.
 *
 * @param array $attachments Attachments from {@see Message::attachments()}.
 * @return array{0:array,1:array} [inlineAttachments, regularAttachments].
 */
    protected function _partitionAttachments(array $attachments): array {
        $inline = [];
        $regular = [];

        foreach ($attachments as $attachment) {
            if ($attachment['disposition'] === 'inline' && !empty($attachment['contentId'])) {
                $inline[] = $attachment;
            } else {
                $regular[] = $attachment;
            }
        }

        return [$inline, $regular];
    }

/**
 * Build a leaf text entity (base64 encoded) for a given content type.
 *
 * @param string $contentType MIME content type (e.g. 'text/plain').
 * @param string $content Raw body content.
 * @param string $charset Character set of the content.
 * @return array{headers:array,body:string} MIME part descriptor.
 */
    protected function _textEntity(string $contentType, string $content, string $charset): array {
        return [
            'headers' => [
                'Content-Type' => sprintf('%s; charset=%s', $contentType, $charset),
                'Content-Transfer-Encoding' => 'base64'
            ],
            'body' => chunk_split(base64_encode($content), 76, self::CRLF)
        ];
    }

/**
 * Build a leaf attachment entity (base64 encoded) from a prepared attachment.
 *
 * @param array $attachment Prepared attachment (file, name, disposition, contentId).
 * @return array{headers:array,body:string} MIME part descriptor.
 */
    protected function _attachmentEntity(array $attachment): array {
        $file = $attachment['file'];
        $name = $this->_encodeHeader((string)$attachment['name']);

        $contentType = $file->mime();
        $disposition = $attachment['disposition'];
        if ($name !== '') {
            $contentType .= sprintf('; name="%s"', $name);
            $disposition .= sprintf('; filename="%s"', $name);
        }

        $headers = [
            'Content-Type' => $contentType,
            'Content-Transfer-Encoding' => 'base64',
            'Content-Disposition' => $disposition
        ];

        if (!empty($attachment['contentId'])) {
            $headers['Content-ID'] = '<' . $attachment['contentId'] . '>';
        }

        return [
            'headers' => $headers,
            'body' => chunk_split($file->getBase64(), 76, self::CRLF)
        ];
    }

/**
 * Wrap child entities in a multipart container with a unique boundary.
 *
 * @param string $subtype Multipart subtype ('alternative', 'related', 'mixed').
 * @param array $parts Child MIME part descriptors.
 * @return array{headers:array,body:string} MIME part descriptor.
 */
    protected function _multipartEntity(string $subtype, array $parts): array {
        $boundary = '=_' . bin2hex(random_bytes(16));

        $body = '';
        foreach ($parts as $part) {
            $body .= '--' . $boundary . self::CRLF;
            $body .= $this->_renderEntity($part) . self::CRLF;
        }
        $body .= '--' . $boundary . '--' . self::CRLF;

        return [
            'headers' => [
                'Content-Type' => sprintf('multipart/%s; boundary="%s"', $subtype, $boundary)
            ],
            'body' => $body
        ];
    }

/**
 * Render a MIME part descriptor into its wire representation.
 *
 * @param array $part MIME part descriptor with 'headers' and 'body'.
 * @return string Rendered headers, blank line and body.
 */
    protected function _renderEntity(array $part): string {
        $lines = [];
        foreach ($part['headers'] as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return implode(self::CRLF, $lines) . self::CRLF . self::CRLF . $part['body'];
    }

/**
 * Format a single address as an RFC 5322 header value with an encoded name.
 *
 * @param \Nata\Email\Address $address Address to format.
 * @return string Formatted address (e.g. '=?UTF-8?B?...?= <a@b.com>').
 */
    protected function _formatAddress($address): string {
        $email = $address->email();
        $personal = (string)$address->personal();

        if ($personal === '') {
            return $email;
        }

        return $this->_encodeHeader($personal) . ' <' . $email . '>';
    }

/**
 * Format an address collection as a comma-separated header value.
 *
 * @param \Nata\Email\AddressCollection $collection Addresses to format.
 * @return string Comma-separated list of formatted addresses.
 */
    protected function _formatAddressList($collection): string {
        $formatted = [];
        foreach ($collection as $address) {
            $formatted[] = $this->_formatAddress($address);
        }
        return implode(', ', $formatted);
    }

/**
 * MIME-encode a header value when it contains non-ASCII characters.
 *
 * ASCII-only values are returned unchanged; others are encoded as folded
 * base64 encoded-words (RFC 2047).
 *
 * @param string $value Header value to encode.
 * @return string Header-safe value.
 */
    protected function _encodeHeader(string $value): string {
        if (preg_match('/[^\x20-\x7e]/', $value) !== 1) {
            return $value;
        }
        return mb_encode_mimeheader($value, 'UTF-8', 'B', self::CRLF);
    }

}
