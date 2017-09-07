<?php

class EasyPay_Provider31
{
        protected static $log;
        
        private $request = array();
        
        protected $operations = array('Check','Payment','Confirm','Cancel');
        
        public function __construct($log_instance)
        {
                self::$log = $log_instance;
        }
        
        public function process()
        {
                try
                {
                        // retrieve data from a POST request
                        $this->get_http_post_raw_data();
                        
                        // parse the data request
                        $this->parse_request_data();
                        
                        $this->raw_response = $this->get_response()->friendly();
                        
                        self::$log->add('the request was processed successfully');
                }
                catch (Exception $e)
                {
                        $this->raw_response = $this->get_error_response($e->getCode(), $e->getMessage())->friendly();
                        
                        self::$log->add('the request was processed with an error');
                }
                
                $this->format_response();
                
                self::$log->debug('response sends: ');
                self::$log->debug($this->formated_response);
                
                ob_clean();
                header("Content-Type: text/xml; charset=utf-8");
                echo $this->formated_response;
                exit;
        }
        
        /**
         *   Format a nice xml output
         *
         */
        private function format_response()
        {
                $xml = new DomDocument();
                $xml->formatOutput = true;
                $xml->preserveWhitespace = false;
                $xml->loadXML($this->raw_response);
                
                $this->formated_response = $xml->saveXML();
        }
        
        /**
         *   Get data from the body of the http request
         *
         *   - with the appropriate configuration of php.ini they can be found
         *     in the global variable $HTTP_RAW_POST_DATA
         *
         *   - but it's easier just to read the data from the php://input stream,
         *     which does not depend on the php.ini directives and allows you to read
         *     raw data from the request body
         */
        private function get_http_post_raw_data()
        {
                $this->raw_request = file_get_contents('php://input');
                
                self::$log->debug('request received: ');
                self::$log->debug($this->raw_request);
                self::$log->debug(' ');
        }
        
        /**
         *   Parse xml-request, which was previously "extracted" from the body of the http request
         */
        private function parse_request_data()
        {
                if ($this->raw_request == NULL)
                {
                        self::$log->error('The xml request from the HTTP request body was not received');
                        throw new Exception('Error in request', -99);
                }
                if (strlen($this->raw_request) == 0)
                {
                        self::$log->error('An empty xml request');
                        throw new Exception('Error in request', -99);
                }
                
                $doc = new DOMDocument();
                $doc->loadXML($this->raw_request);
                $r = $this->getNodes($doc, 'Request');
                
                if (count($r) != 1)
                {
                        self::$log->error('There is more than one Request element in the xml-query!');
                        throw new Exception('Error in request', -99);
                }
                
                foreach ($r[0]->childNodes as $child)
                {
                        if ($child->nodeName == 'DateTime')
                        {
                                if ( ! isset($this->request['DateTime']))
                                {
                                        $this->request['DateTime'] = $child->nodeValue;
                                }
                                else
                                {
                                        self::$log->error('There is more than one DateTime element in the xml-query!');
                                        throw new Exception('Error in request', -99);
                                }
                        }
                        elseif ($child->nodeName == 'Sign')
                        {
                                if ( ! isset($this->request['Sign']))
                                {
                                        $this->request['Sign'] = $child->nodeValue;
                                }
                                else
                                {
                                        self::$log->error('There is more than one Sign element in the xml-query!');
                                        throw new Exception('Error in request', -99);
                                }
                        }
                        elseif (in_array($child->nodeName, $this->operations))
                        {
                                if ( ! isset($this->request['Operation']))
                                {
                                        $this->request['Operation'] = $child->nodeName;
                                }
                                
                                $this->request[$child->nodeName] = array();
                                $o = $child;
                        }
                }
                var_dump($this);
                
                $this->validate_request();
                
                throw new Exception('to do', -77);
        }
        
        /**
         *   "Rough" validation of the received xml request
         *   we are checking only the Request node
         *
         *   must contain child elements
         */
        private function validate_request()
        {
                if ( ! isset($this->request['DateTime']))
                {
                        Log::instance()->error('There is no DateTime element in the xml request!');
                        throw new Exception('Error in request', -99);
                }
                if ( ! isset($this->request['Sign']))
                {
                        Log::instance()->error('There is no DateTime element in the xml request!');
                        throw new Exception('Error in request', -99);
                }
                if ( ! isset($this->request['Operation']))
                {
                        Log::instance()->error('There is no Operation type element in the xml request!');
                        throw new Exception('Error in request', -99);
                }
        }
        
        /**
         *   Generates an xml with an error message
         */
        private function get_error_response($code, $message)
        {
                /**
                 *  Sending a response with an error code
                 */
                
                require_once('Provider31/Response.php');
                require_once('Provider31/Response/ErrorInfo.php');
                $errxml = new EasyPay_Provider31_Response_ErrorInfo($code, $message);
                
                return $errxml;
        }
        
        /**
         *   Selects nodes by name
         */
        private function getNodes($dom, $name, $ret=array())
        {
                foreach($dom->childNodes as $child)
                {
                        if ($child->nodeName == $name)
                        {
                                array_push($ret, $child);
                        }   
                        else
                        {
                                if (count($child->childNodes) > 0)
                                {
                                        $ret = $this->getNodes($child, $name, $ret);
                                }
                        }
                }
                
                return $ret;
        }
}