<?php

namespace WeAreDevelopers\Utils;

class Email
{
    /**
     * Verify with the mail server that the email exists.
     *
     * Note:
     * yahoo.com will sometimes send a 421 response for the from address
     * and it will also sometimes send a 45[0-9] response for the to address
     * both can occur when the email address does actually exist.
     *
     * At the same time yahoo will also send a 250 response for both the to and from
     * even if the email doesn't exist, that is if the above hasn't occurred.
     *
     * This is why we have a strict parameter.
     * If set to true, all yahoo.com email addresses will be marked as invalid.
     *
     * Here, if you can make any sense of it, or find a reliable way to check the validity of their emails addresses:
     * https://help.yahoo.com/kb/postmaster/
     * https://help.yahoo.com/kb/postmaster/smtp-error-code-table-sln23996.html
     *
     * @param string $to
     * @param string $from
     * @param bool $strict Defaults to false. Marks all yahoo.com email addresses as invalid.
     * @param array $validCodes
     * @param bool $getDetails Defaults to false.
     * 
     * @return bool|object
     */
    public static function exists($to, $from, $strict = false, $validCodes = ['250'], $getDetails = false)
    {
        // set variables
        $details = [];

        // get domain from to email
        $domain = static::getDomain($to);

        // try get mail exchange IP address
        $mxIp = static::getMXIpAddress($domain);
        if ($mxIp === false) {
            $valid = false;
            $details[] = 'Could not find MX records';
        } else {
            $connect = @fsockopen($mxIp, 25);
            if ($connect && preg_match("/^220/i", $out = fgets($connect, 1024))) {
                // greet mx server
                $details['greeting'] = static::getResponse($connect, 'HELO ' . $mxIp);
                // set from address
                $fromResponse = $details['from'] = static::getResponse($connect, 'MAIL FROM: <' . $from . '>');
                // set to address
                $toResponse = $details['to'] = static::getResponse($connect, 'RCPT TO: <' . $to . '>');

                // close connection
                fputs($connect, "QUIT");
                fclose($connect);

                // check response to validate email address
                if (
                    !static::isValid($fromResponse, $validCodes) ||
                    !static::isValid($toResponse, $validCodes) ||
                    ($strict && $domain == 'yahoo.com')
                ) {
                    // yahoo stupidity check
                    if (
                        $domain == 'yahoo.com' &&
                        ($toResponse == false || preg_match("/^45[0-9]/i", $toResponse) || preg_match("/^421/i", $fromResponse))
                    ) {
                        $valid = true;
                        $details['yahoo'] = 'Best guess is that this address does in fact exist, but don\'t trust yahoo\'s mx servers... ever';
                    } else {
                        $valid = false;
                    }
                } else {
                    $valid = true;
                }
            } else {
                $valid = false;
                $details[] = 'Could not connect to server';
            }
        }

        if ($getDetails) {
            return (object)[
                'valid' => $valid,
                'details' => $details,
            ];
        } else {
            return $valid;
        }
    }

    /**
     * Checks if a response includes one of the valid response codes.
     *
     * @param string $response
     * @param array $codes
     * @return bool
     */
    protected static function isValid($response, $codes)
    {
        $valid = false;
        foreach ($codes as $code) {
            if (preg_match("/^$code/i", $response)) {
                $valid = true;
                break;
            }
        }

        return $valid;
    }

    /**
     * @param resource $connect
     * @param string $query
     * @return mixed
     */
    protected static function getResponse($connect, $query)
    {
        fputs($connect, $query . "\r\n");
        $response = fgets($connect, 1024);
        return is_string($response) ? trim($response) : $response;
    }

    /**
     * @param string $email
     * @return string
     */
    public static function getDomain($email)
    {
        $pieces = explode("@", $email);
        $domain = array_slice($pieces, -1)[0];
        // Trim [ and ] from beginning and end of domain string, respectively
        $domain = ltrim($domain, "[");
        $domain = rtrim($domain, "]");
        if ("IPv6:" == substr($domain, 0, strlen("IPv6:"))) {
            $domain = substr($domain, strlen("IPv6") + 1);
        }

        return $domain;
    }

    /**
     * @param string $domain
     * @return string|bool
     */
    public static function getMXIpAddress($domain)
    {
        $mxIp = null;

        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $mxIp = $domain;

            if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $record_a = dns_get_record($domain, DNS_A);
            } elseif (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $record_a = dns_get_record($domain, DNS_AAAA);
            }

            if (!empty($record_a)) {
                $mxIp = $record_a[0]['ip'];
            }
        } else {
            $mxHosts = [];
            $mxWeight = '';
            getmxrr($domain, $mxHosts, $mxWeight);
            $mxIp = $mxHosts[array_search(min($mxWeight), $mxHosts)];
        }

        return $mxIp ?: false;
    }
}
