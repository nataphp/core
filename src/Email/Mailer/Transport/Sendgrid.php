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
use Nata\Core\Exception as CoreException;
use Nata\Email\AddressCollection;
use Nata\Email\Mailer\Message;
use Nata\Email\Mailer\Transport;
use Nata\Email\Mailer\Response;
use Nata\Http\Client;

/**
 * Sendgrid email transport API.
 */
class Sendgrid extends Transport {

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'apiKey' => null
    ];


/**
 * Send.
 *
 * @param Message $message Email message to send
 * @return bool True if successful, false otherwise.
 */
    public function send(Message $message): bool {
        parent::_uniquifyAddresses($message);

        $data = $this->_prepareData($message);
        // print_a(json_encode($data, JSON_PRETTY_PRINT));die;
        return $this->_send($data, $message);
    }

/**
 * Initialize PHPMailer as transport.
 *
 * @param Message $message Email HTML and/or Text messages.
 * @return mixed False if no error, Error message otherwise
 */
    protected function _send(array $data, Message $message) {
        $apiKey = $this->config('apiKey');
        if (empty($apiKey)) {
            throw new MissingConfigurationException(sprintf('Missing API Key for Sendgrid transport.'));
        }

        $request = Client::post([
            'url' => 'https://api.sendgrid.com/v3/mail/send',
            'header' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ],
            'data' => $data
        ]);

        $response = Client::send($request);
        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        if (strpos($body, '{') === 0) {
            $body = json_decode($body, true);
        }

        if (is_array($body) && isset($body['errors'])) {
            $this->_prepareResponseErrors($body['errors']);
            return false;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->_errors[] = $response->getStatus();
            return false;
        }

        $transportId = $response->getHeader('X-Message-Id');
        if (!$transportId) {
            $this->_errors[] = new CoreException([
                'message' => $response->getStatus() . ' - Missing X-Message-Id from response.'
            ]);
            return false;
        }

        if (!$message->messageId()) {
            $message->messageId(sprintf('<%s@%s>', $transportId, 'sendgrid-tmp'));
        }

        $this->_id = $transportId;
        $this->_smtpReply = '200 2.0.0 OK: waiting for webhook';

        return true;

        return new Response([
            'transportId' => $transportId,
            'smtpReply' => '200 2.0.0 OK: waiting for webhook',
            'statusCode' => $statusCode
        ]);
    }

/**
 * Prepare data for Sendgrid API.
 *
 * @param Message $message Email message.
 * @return array Data to be sent
 */
    protected function _prepareData(Message $message) {
        $data = [
            'personalizations' => [],
            'subject' => $message->subject(),
            'content' => []
        ];

        if ($message->from() && !$message->from()->isEmpty()) {
            $data['from'] = $message->from();
        }

        if ($message->replyTo() && !$message->replyTo()->isEmpty()) {
            $data['reply_to_list'] = $message->replyTo();
        }

        // "TO" personalization is required by the API v3
        $data['personalizations'][0]['to'] = $this->_getPersonalizationTo($message);

        if ($message->cc() && !$message->cc()->isEmpty()) {
            $data['personalizations'][0]['cc'] = $message->cc();
        }

        if ($message->bcc() && !$message->bcc()->isEmpty()) {
            $data['personalizations'][0]['bcc'] = $message->bcc();
        }

        if ($textMessage = $message->textMessage()) {
            $data['content'][] = [
                'type' => 'text/plain',
                'value' => $textMessage
            ];
        }

        if ($htmlMessage = $message->htmlMessage()) {
            $data['content'][] = [
                'type' => 'text/html',
                'value' => $htmlMessage
            ];
        }

        if (!empty($message->attachments())) {
            $data['attachments'] = [];
            foreach ($message->attachments() as $attachment) {
                $file = $attachment['file'];

                $attach = [
                    'content' => $file->getBase64(),
                    'type' => $file->mime(),
                    'filename' => $attachment['name'],
                    'disposition' => $attachment['disposition']
                ];

                if ($attachment['contentId']) {
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
 * Get the personalization for "TO".
 *
 * This makes sure that the 'to' has always an address, using CC as fallback.
 *
 * @param Message $message Email message.
 * @see https://docs.sendgrid.com/for-developers/sending-email/personalizations#third-pane
 * @return AddressCollection|Address
 */
    protected function _getPersonalizationTo(Message $message) {
        if ($message->to() && !$message->to()->isEmpty()) {
            return $message->to();
        }
        if ($message->cc() && !$message->cc()->isEmpty()) {
            return $message->cc()->consume(0);
        }
        return $message->bcc()->consume(0);
    }

/**
 * Normalize Sendgrid's API response errors.
 *
 * @param array $errors Response errors.
 * @return void
 */
    protected function _prepareResponseErrors(array $errors) {
        foreach ($errors as $error) {
            $error += [
                'message' => null,
                'field' => null,
                'help' => null
            ];

            //$this->_errors[] = new CoreException($error);
            $this->_errors[] = $error;
        }
    }

}
