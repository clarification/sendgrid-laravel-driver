<?php

namespace Clarification\MailDrivers\Sendgrid\Transport;

use Swift_Image;
use Swift_MimePart;
use Swift_Attachment;
use Swift_Mime_SimpleMessage;
use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;

class SendgridTransport extends Transport
{
    const MAXIMUM_FILE_SIZE = 7340032;
    const SMTP_API_NAME = 'sendgrid/x-smtpapi';

    protected $client;
    protected $options;

    protected $incrementer = 0;

    public function __construct(ClientInterface $client, $api_key)
    {
        $this->client = $client;
        $this->options = [
            'headers' => ['Authorization' => 'Bearer ' . $api_key]
        ];
    }

    /**
     * Send the given Message.
     *
     * Recipient/sender data will be retrieved from the Message API.
     * The return value is the number of recipients who were accepted for delivery.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param string[]           $failedRecipients An array of failures by-reference
     *
     * @return int
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        list($from, $fromName) = $this->getFromAddresses($message);
        $payload = $this->options;
        $data = [
            'from'     => $from,
            'fromname' => isset($fromName) ? $fromName : null,
            'subject'  => $message->getSubject(),
            'html'     => $message->getBody()
        ];

        $this->setTo($data, $message);
        $this->setCc($data, $message);
        $this->setBcc($data, $message);
        $this->setText($data, $message);
        $this->setReplyTo($data, $message);
        $this->setAttachment($data, $message);
        $this->setSmtpApi($data, $message);

        if (version_compare(ClientInterface::VERSION, '6') === 1) {
            $payload += ['form_params' => $data];
        } else {
            $payload += ['body' => $data];
        }

        $response = $this->client->post('https://api.sendgrid.com/api/mail.send.json', $payload);

        $this->sendPerformed($message);

        return $response;
    }

    /**
     * @param  $data
     * @param  Swift_Mime_SimpleMessage $message
     */
    protected function setTo(&$data, Swift_Mime_SimpleMessage $message)
    {
        if ($to = $message->getTo()) {
            $data['to'] = array_keys($to);
            $data['toname'] = array_values($to);
        }
    }

    /**
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setCc(&$data, Swift_Mime_SimpleMessage $message)
    {
        if ($cc = $message->getCc()) {
            $data['cc'] = array_keys($cc);
            $data['ccname'] = array_values($cc);
        }
    }

    /**
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setBcc(&$data, Swift_Mime_SimpleMessage $message)
    {
        if ($bcc = $message->getBcc()) {
            $data['bcc'] = array_keys($bcc);
            $data['bccname'] = array_values($bcc);
        }
    }

    /**
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setReplyTo(&$data, Swift_Mime_SimpleMessage $message)
    {
        if ($replyTo = $message->getReplyTo()) {
            $data['replyto'] = array_keys($replyTo);
        }
    }

    /**
     * Get From Addresses.
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array
     */
    protected function getFromAddresses(Swift_Mime_SimpleMessage $message)
    {
        if ($from = $message->getFrom()) {
            foreach ($from as $address => $name) {
                return [$address, $name];
            }
        }

        return [];
    }

    /**
     * Set text contents.
     *
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setText(&$data, Swift_Mime_SimpleMessage $message)
    {
        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_MimePart) {
                continue;
            }
            $data['text'] = $attachment->getBody();
        }
    }

    /**
     * Set Attachment Files.
     *
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setAttachment(&$data, Swift_Mime_SimpleMessage $message)
    {
        $attachments = $message->getChildren();

        if (!isset($data['files']) && count($attachments) > 0) {
            $data['files'] = [];
        }

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof Swift_Attachment || strlen($attachment->getBody()) > self::MAXIMUM_FILE_SIZE) {
                continue;
            }
            $attachmentName = $attachment->getFilename();
            if (isset($data['files'][$attachmentName])) {
                $attachmentName .= ++$this->incrementer;
            }

            $handler = tmpfile();
            fwrite($handler, $attachment->getBody());
            $data['files'][$attachmentName] = $handler;
        }
    }

    /**
     * Set Sendgrid SMTP API
     *
     * @param $data
     * @param Swift_Mime_SimpleMessage $message
     */
    protected function setSmtpApi(&$data, Swift_Mime_SimpleMessage $message)
    {
        foreach ($message->getChildren() as $attachment) {
            if (!$attachment instanceof Swift_Attachment || $attachment instanceof Swift_Image) {
                continue;
            }
            if (in_array(self::SMTP_API_NAME, [$attachment->getFilename(), $attachment->getContentType()])) {
                $data['x-smtpapi'] = $attachment->getBody();
            }
        }
    }
}
