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

use MissingConfigurationException;
use Nata\Email\AddressCollection;
use Nata\Email\Mailer\Message;
use Nata\Email\Mailer\Transport;
use Nata\Http\Client;

/**
 * Email transport using the Resend HTTP API (POST /emails).
 *
 * @link https://www.resend.com/docs/api-reference/emails/send-email
 */
class Resend extends Transport {

/**
 * Default configuration.
 *
 * - apiKey: Resend API key used as the Bearer token.
 *
 * @var array<string, mixed>
 */
    protected $_defaultConfig = [
        'apiKey' => null
    ];

/**
 * Send the message via Resend.
 *
 * Validates that at least one of HTML or text body is present, builds the JSON
 * payload, and POSTs it to the Resend API.
 *
 * @param \Nata\Email\Mailer\Message $message Email message to send.
 * @return bool True if successful, false otherwise.
 */
    public function send(Message $message): bool {
        parent::_uniquifyAddresses($message);

        if (trim((string) $message->htmlMessage()) === '' && trim((string) $message->textMessage()) === '') {
            $this->_errors[] = 'Resend transport requires a non-empty html or text body.';
            return false;
        }

        $data = $this->_prepareData($message);
        return $this->_send($data, $message);
    }

/**
 * POST the JSON payload to Resend and handle the response.
 *
 * @param array<string, mixed> $data Request body for POST https://api.resend.com/emails
 * @param \Nata\Email\Mailer\Message $message Email message (for Message-ID on success).
 * @return bool True on success, false on failure.
 */
    protected function _send(array $data, Message $message): bool {
        $apiKey = $this->config('apiKey');
        if (empty($apiKey)) {
            throw new MissingConfigurationException(sprintf('Missing API Key for Resend transport.'));
        }

        $request = Client::post([
            'url' => 'https://api.resend.com/emails',
            'header' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'data' => $data
        ]);

        $response = Client::send($request);
        $statusCode = $response->getStatusCode();
        $bodyRaw = $response->getBody();

        $body = $bodyRaw;
        if (is_string($bodyRaw) && strpos($bodyRaw, '{') === 0) {
            $decoded = json_decode($bodyRaw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $body = $decoded;
            }
        }

        $hasApiErrorShape = is_array($body)
            && (
                (isset($body['error']) && is_array($body['error']))
                || !empty($body['errors'])
                || (isset($body['message']) && isset($body['name']))
            );

        if ($statusCode < 200 || $statusCode >= 300) {
            if (is_array($body)) {
                $this->_appendResendErrorBody($body);
            }
            if ($this->_errors === []) {
                $this->_errors[] = (string) $response->getStatus();
            }

            return false;
        }

        if ($hasApiErrorShape) {
            $this->_appendResendErrorBody($body);

            return false;
        }

        if (!is_array($body) || empty($body['id'])) {
            $this->_errors[] = (string) $response->getStatus() . ' - Missing id in Resend response.';
            return false;
        }

        $transportId = (string) $body['id'];

        if (!$message->messageId()) {
            $message->messageId(sprintf('<%s@%s>', $transportId, 'resend'));
        }

        $this->_id = $transportId;
        $this->_smtpReply = '200 2.0.0 OK: queued by Resend';

        return true;
    }

/**
 * Build the Resend JSON body (flat from, to, html, text, etc.).
 *
 * @param \Nata\Email\Mailer\Message $message Email message.
 * @return array<string, mixed> Payload for POST /emails
 */
    protected function _prepareData(Message $message): array {
        $data = [
            'subject' => $message->subject(),
        ];

        if ($message->from() && !$message->from()->isEmpty()) {
            $data['from'] = (string) $message->from();
        }

        $data['to'] = $this->_collectionStrings($this->_getPersonalizationTo($message));

        if ($message->cc() && !$message->cc()->isEmpty()) {
            $data['cc'] = $this->_collectionStrings($message->cc());
        }

        if ($message->bcc() && !$message->bcc()->isEmpty()) {
            $data['bcc'] = $this->_collectionStrings($message->bcc());
        }

        if ($message->replyTo() && !$message->replyTo()->isEmpty()) {
            $data['reply_to'] = $this->_collectionStrings($message->replyTo());
        }

        if ($textMessage = $message->textMessage()) {
            $data['text'] = $textMessage;
        }

        if ($htmlMessage = $message->htmlMessage()) {
            $data['html'] = $htmlMessage;
        }

        if (!empty($message->attachments())) {
            $data['attachments'] = [];
            foreach ($message->attachments() as $attachment) {
                $file = $attachment['file'];

                $attach = [
                    'content' => $file->getBase64(),
                    'filename' => $attachment['name'],
                ];

                $mime = $file->mime();
                if (!empty($mime)) {
                    $attach['content_type'] = $mime;
                }

                if (!empty($attachment['contentId'])) {
                    $attach['content_id'] = $attachment['contentId'];
                }

                $data['attachments'][] = $attach;
            }
        }

        if ($message->header()) {
            $data['headers'] = $message->header();
        }

        return $data;
    }

/**
 * RFC 822 strings for each address in the collection (Resend expects string lists).
 *
 * @param \Nata\Email\AddressCollection $collection Recipient addresses.
 * @return list<string>
 */
    protected function _collectionStrings(AddressCollection $collection): array {
        return array_map('strval', iterator_to_array($collection));
    }

/**
 * Resolve primary recipients for the Resend `to` field (same rules as SendGrid personalization).
 *
 * Ensures at least one mailbox using To, else first CC, else first BCC.
 *
 * @param \Nata\Email\Mailer\Message $message Email message.
 * @return \Nata\Email\AddressCollection
 */
    protected function _getPersonalizationTo(Message $message): AddressCollection {
        if ($message->to() && !$message->to()->isEmpty()) {
            return $message->to();
        }
        if ($message->cc() && !$message->cc()->isEmpty()) {
            return $message->cc()->consume(0);
        }

        return $message->bcc()->consume(0);
    }

/**
 * Append human-readable error strings from a decoded Resend error JSON body.
 *
 * @param array<string, mixed> $body Decoded JSON response body.
 * @return void
 */
    protected function _appendResendErrorBody(array $body): void {
        if (isset($body['error']) && is_array($body['error'])) {
            $e = $body['error'];
            $this->_errors[] = ($e['name'] ?? 'error') . ': ' . ($e['message'] ?? '');

            return;
        }

        if (isset($body['message'])) {
            $this->_errors[] = ($body['name'] ?? 'error') . ': ' . (string) $body['message'];

            return;
        }

        if (!empty($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $error) {
                if (is_string($error)) {
                    $this->_errors[] = $error;
                } elseif (is_array($error)) {
                    $this->_errors[] = ($error['name'] ?? 'error') . ': ' . ($error['message'] ?? json_encode($error));
                }
            }
        }
    }

}
