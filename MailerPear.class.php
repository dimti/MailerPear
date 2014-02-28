<?php
/**
 * Description: Integrate wp-referral into bp-automatic-friends for create friendships from referral code
 * Version: 0.2.00
 * Author: Alexander Demidov
 * Author Email: dimti@bk.ru
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

include 'Mail.php';
include 'Mail/mime.php';

/**
 * Class MailerPear
 */
class MailerPear extends Mailer
{
    const ATTACHMENT_FILE = 'file';
    const ATTACHMENT_C_TYPE = 'c_type';
    const ATTACHMENT_NAME = 'name';
    const ATTACHMENT_ISFILE = 'isfile';

    protected $headers = array();

    protected $host;

    protected $to;

    protected $from;

    protected $return_path;

    protected $sender_name;

    protected $recipient_name;

    private $clrf;

    /**
     * @param $host string
     */
    public function __construct($host)
    {
        $this->host = $host;
        $this->setDefaultHeaders();
        /**
         * @desc hook for deine clrf
         * @author Stephen Bigelis
         * @see http://pear.php.net/bugs/bug.php?id=12032
         */
        if (!defined('PHP_EOL')) define ('PHP_EOL', strtoupper(substr(PHP_OS,0,3) === 'WIN') ? "\r\n" : "\n");
        $this->clrf = PHP_EOL;
    }

    /**
     * @param $email string
     * @param $subject string
     * @param $message null|string
     * @param $message_txt null|string
     * @param $html_images null|array
     * @param $attachments null|array
     * @return bool|void
     */
    public function send($email, $subject, $message = null, $message_txt = null, $html_images = null, $attachments = null)
    {
        $mime = new Mail_mime(
            array(
                'eol' => $this->clrf,
                'text_encoding' => 'base64',
                'text_charset' => 'utf-8',
                'html_encoding' => 'quoted-printable',
                'html_charset' => 'utf-8',
            )
        );
        if (!is_null($message_txt)) {
            $mime->setTXTBody($message_txt);
        }
        if (!is_null($message)) {
            $mime->setHTMLBody($message);
        }
        if (!is_null($html_images)) {
            foreach ($html_images as $html_image_item) {
                $mime->addHTMLImage($html_image_item);
            }
        }
        if (!is_null($attachments)) {
            foreach ($attachments as $attachment_item) {
                if (is_array($attachment_item)) {
                    if (!isset($attachment_item[self::ATTACHMENT_FILE])) {
                        continue;
                    }
                    $mime->addAttachment(
                        $attachment_item[self::ATTACHMENT_FILE],
                        isset($attachment_item[self::ATTACHMENT_C_TYPE]) ? $attachment_item[self::ATTACHMENT_C_TYPE] : 'application/octet-stream',
                        isset($attachment_item[self::ATTACHMENT_NAME]) ? $attachment_item[self::ATTACHMENT_NAME] : '',
                        isset($attachment_item[self::ATTACHMENT_ISFILE]) ? $attachment_item[self::ATTACHMENT_ISFILE] : true
                    );
                } else {
                    $mime->addAttachment($attachment_item);
                }
            }
        }
        $this->headers['From'] = $this->getSenderHeader();
        $this->headers['Return-Path'] = $this->getReturnPathHeader();
        $this->headers['To'] = $this->getRecipientHeader($email);
        $this->headers['Subject'] = $this->getSubjectHeader($subject);
        $headers = $mime->headers($this->headers, true, false);

        $mail = new Mail();
        $mail = $mail->factory('sendmail');
        $body = $mime->get();
        $mail->send($email, $headers, $body);
        $this->resetState();
    }

    public function resetState()
    {
        $this->from = null;
        $this->to = null;
        $this->sender_name = null;
        $this->recipient_name = null;
        $this->headers = array();
        $this->setDefaultHeaders();
    }

    private function setDefaultHeaders()
    {
        $this->headers['Date'] =  date('r');
        $this->headers['X-Priority'] = '3 (Normal)';
        $this->headers['Message-ID'] = '<'. md5(uniqid(time())).'@'. $this->host .'>';
    }

    private function getSubjectHeader($subject)
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?= ';
    }

    /**
     * @return string
     */
    private function getSenderHeader()
    {
        $header_sender = '';
        if (!is_null($this->sender_name)) {
            $header_sender = '=?UTF-8?B?' . base64_encode($this->sender_name) . '?= ';
        }
        if (!is_null($this->from)) {
            $header_sender .= '<' . $this->from . '>';
        } else {
            $header_sender .= '<' . 'noreply@' . $this->host . '>';
        }
        return $header_sender;
    }

    /**
     * @return string
     */
    private function getReturnPathHeader()
    {
        $header_return_path = '';
        if ($this->return_path) {
            if (!is_null($this->sender_name)) {
                $header_return_path = '=?UTF-8?B?' . base64_encode($this->sender_name) . '?= ';
            }
            if (!is_null($this->return_path)) {
                $header_return_path .= '<' . $this->return_path . '>';
            } else {
                $header_return_path .= '<' . 'noreply@' . $this->host . '>';
            }
        }
        return $header_return_path;
    }

    /**
     * @param $email
     * @return string
     */
    private function getRecipientHeader($email)
    {
        $header_recipient = '';
        if (!is_null($this->recipient_name)) {
            $header_recipient = '=?UTF-8?B?' . base64_encode($this->recipient_name) . '?= ';
        }
        $header_recipient .= '<' . $email . '>';
        return $header_recipient;
    }

    /**
     * @param $from string
     * @desc Set the E-mail Address of sender
     * @desc That address as Return-Path and replacement system user name, then execute php-script
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @param $sender_name
     * @desc Set the Name of send
     */
    public function setSender($sender_name)
    {
        $this->sender_name = $sender_name;
    }

    /**
     * @param $to string
     * @desc Set the E-mail Address of recipient
     */
    public function setTo($to)
    {
        $this->to = $to;
    }

    /**
     * @param $recipient_name
     * @desc Set the name of recipient
     */
    public function setRecipient($recipient_name)
    {
        $this->recipient_name = $recipient_name;
    }

    /**
     * @param $return_path string
     */
    public function setReturnPath($return_path)
    {
        $this->return_path = $return_path;
    }
}