<?php

/**
 *      Abstract class for the family of response classes
 *
 *      @package php_EasyPay
 *      @version 1.0
 *      @author Dmitry Shovchko <d.shovchko@gmail.com>
 *
 */

namespace EasyPay\Provider31;

use EasyPay\Log as Log;

abstract class Response extends \DomDocument
{
        /**
         *      @var string
         *
         */
        const TEMPLATE = '<Response><StatusCode></StatusCode><StatusDetail></StatusDetail><DateTime></DateTime></Response>';
        
        /**
         *      Response constructor
         *      
         */
        function __construct()
        {
                parent::__construct('1.0', 'UTF-8');

                self::loadXML(self::TEMPLATE);
                
                $this->Response = $this->firstChild;
                $this->setElementValue('DateTime', date('Y-m-d\TH:i:s', time()));
        }
        
        /**
         *      Create new element node
         *
         *      @param string $name
         *      @param string $value (optional)
         */
        function createElement($name, $value=NULL)
        {
                return parent::createElement($name, $value);
        }
        
        /**
         *      Create new node attribute
         *
         *      @param string $name
         *      @param string $value
         */
        function create_attr($name, $value)
        {
                return new DOMAttr($name, $value);
        }
        
        /**
         *      Set node value
         *
         *      @param string $name
         *      @param string $value
         */
        function setElementValue($name, $value)
        {
                foreach ($this->Response->childNodes as $child)
                {
                        if ($child->nodeName == $name)
                        {
                                $child->nodeValue = $value;
                        }
                }
        }
        
        /**
         *      Dumps response into a string
         *
         *      @return string XML
         */
        function friendly()
        {
                $this->encoding = 'UTF-8';
                $this->formatOutput = true;
                //$this->save('/tmp/test1.xml');

                return $this->saveXML(NULL, LIBXML_NOEMPTYTAG);
        }
        
        /**
         *      Send response
         *
         *      @param array $options
         */
        function out($options)
        {
                if ($options['UseSign'] === true)
                {
                        $this->Sign = self::createElement('Sign');
                        $this->Response->appendChild($this->Sign);
                        
                        $sign = $this->generate_sign($options);
                        
                        $this->Sign->nodeValue = $sign;
                }
                
                Log::instance()->debug('response sends: ');
                Log::instance()->debug($this->friendly());
                
                ob_clean();
                header("Content-Type: text/xml; charset=utf-8");
                echo $this->friendly();
                exit;
        }
        
        /**
         *      Generate signature of response
         *
         *      @param array $options
         *      @return string
         */
        function generate_sign($options)
        {
                if ( ! file_exists($options['ProviderPKey']))
                {
                        Log::instance()->error('The file with the public key Provider was not find!');
                        return null;
                }
                
                // this code is written according to the easysoft example

                $fpkey = fopen($options['ProviderPKey'], "rb");
                if ($fpkey === FALSE)
                {
                        Log::instance()->error('The file with the public key Provider was not open!');
                        return null;
                }
                $pkeyid = fread($fpkey, 8192);
                if ($pkeyid === FALSE)
                {
                        Log::instance()->error('The file with the public key Provider was not read!');
                        return null;
                }
                fclose($fpkey);
                
                $pr_key = openssl_pkey_get_private($pkeyid);
                if ($pr_key === FALSE)
                {
                        Log::instance()->error('Can not extract the private key from certificate!');
                        return null;
                }
                if (openssl_sign($this->friendly(), $sign, $pr_key) === FALSE)
                {
                        Log::instance()->error('Can not generate signature!');
                        return null;
                }
                
                return strtoupper(bin2hex($sign));
        }
}