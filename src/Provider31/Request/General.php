<?php

/**
 *      General class for all request types
 *
 *      @package php_EasyPay
 *      @version 1.1
 *      @author Dmitry Shovchko <d.shovchko@gmail.com>
 *
 */

namespace EasyPay\Provider31\Request;

use EasyPay\Log as Log;
use EasyPay\Exception;
use EasyPay\Sign as Sign;
use EasyPay\Provider31\Request\RAW as RAW;

class General
{
    /**
     *      @var \EasyPay\Provider31\Request\RAW raw request
     */
    protected $raw_request;

    /**
     *      @var string 'DateTime' node
     */
    protected $DateTime;

    /**
     *      @var string 'Sign' node
     */
    protected $Sign;

    /**
     *      @var string 'Operation' type
     */
    protected $Operation;

    /**
     *      @var string 'ServiceId' node
     */
    protected $ServiceId;

    /**
     *      @var array list of possible operations
     */
    protected $operations = array('Check','Payment','Confirm','Cancel');

    /**
     *      General constructor
     *
     *      @param \EasyPay\Provider31\Request\RAW $raw Raw request data
     */
    public function __construct($raw)
    {
        $this->raw_request = $raw;

        $this->parse_request_data();
    }

    /**
     *      Get DateTime
     *
     *      @return string
     */
    public function DateTime()
    {
        return $this->DateTime;
    }

    /**
     *      Get Sign
     *
     *      @return string
     */
    public function Sign()
    {
        return $this->Sign;
    }

    /**
     *      Get Operation type
     *
     *      @return string
     */
    public function Operation()
    {
        return $this->Operation;
    }

    /**
     *      Get ServiceId
     *
     *      @return string
     */
    public function ServiceId()
    {
        return $this->ServiceId;
    }

    /**
     *      Parse xml-request, which was previously "extracted" from the body of the http request
     *      processes contents of <Request> node
     */
    protected function parse_request_data()
    {
        $r = $this->check_request_count(
            $this->raw_request->get_nodes_from_request('Request')
        );

        foreach ($r[0]->childNodes as $child)
        {
            $this->check_and_parse_request_node($child, 'DateTime');
            $this->check_and_parse_request_node($child, 'Sign');

            $this->check_and_parse_operation($child);
        }
        $this->parse_operation_data();
    }

    /**
     *      Check count of <Request> nodes in xml-request
     *
     *      @throws Exception\Structure
     */
    protected function check_request_count($ar)
    {
        if (count($ar) < 1)
        {
            throw new Exception\Structure('The xml-query does not contain any element Request!', -52);
        }
        elseif (count($ar) > 1)
        {
            throw new Exception\Structure('The xml-query contains several elements Request!', -52);
        }

        return $ar;
    }

    /**
     *      Parse xml-request, which was previously "extracted" from the body of the http request
     *      processes contents of <Operation> node
     *
     *      @throws Exception\Structure
     */
    protected function parse_operation_data()
    {
        $this->check_presence_operation();

        $r = $this->raw_request->get_nodes_from_request($this->Operation);

        foreach ($r[0]->childNodes as $child)
        {
            $this->check_and_parse_request_node($child, 'ServiceId');
        }
    }

    /**
     *      Check if present node of request and then parse it
     *
     *      @param \DOMNode $n
     *      @param string $name
     */
    protected function check_and_parse_request_node($n, $name)
    {
        if ($n->nodeName == $name)
        {
            $this->parse_request_node($n, $name);
        }
    }

    /**
     *      Check if given node is Operation
     *
     *      @throws Exception\Structure
     */
    protected function check_and_parse_operation($n)
    {
        if (in_array($n->nodeName, $this->operations))
        {
            if ( ! isset($this->Operation))
            {
                $this->Operation = $n->nodeName;
            }
            else
            {
                throw new Exception\Structure('There is more than one Operation type element in the xml-query!', -53);
            }
        }
    }

    /**
     *      Check if Operation present in request
     *
     *      @throws Exception\Structure
     */
    protected function check_presence_operation()
    {
        if ( ! isset($this->Operation))
        {
            throw new Exception\Structure('There is no Operation type element in the xml request!', -55);
        }
    }

    /**
     *      Parse node of request
     *
     *      @param \DOMNode $n
     *      @param string $name
     *
     *      @throws Exception\Structure
     */
    protected function parse_request_node($n, $name)
    {
        if ( ! isset($this->$name))
        {
            $this->$name = $n->nodeValue;
        }
        else
        {
            throw new Exception\Structure('There is more than one '.$name.' element in the xml-query!', -56);
        }
    }

    /**
     *      "Rough" validation of the received xml request
     *
     *      @param array $options
     *      @throws Exception\Data
     *      @throws Exception\Structure
     */
    public function validate_request($options)
    {
        $this->validate_element('DateTime');
        $this->validate_element('Sign');
        $this->validate_element('ServiceId');

        // compare received value ServiceId with option ServiceId
        if (intval($options['ServiceId']) != intval($this->ServiceId))
        {
            throw new Exception\Data('This request is not for our ServiceId!', -58);
        }
    }

    /**
     *      Validation of xml-element
     *
     *      @param string $name
     */
    public function validate_element($name)
    {
        if ( ! isset($this->$name))
        {
            throw new Exception\Structure('There is no '.$name.' element in the xml request!', -57);
        }
    }

    /**
     *      Verify signature of request
     *
     *      @param array $options
     */
    public function verify_sign($options)
    {
        $sign = new Sign();
        $sign->verify(
            $this->raw_request->str(),
            $this->Sign,
            $options
        );
    }

}
