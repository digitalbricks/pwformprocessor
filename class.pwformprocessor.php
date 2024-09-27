<?php

/**
 * Class PwFormprocessor
 */
class PwFormprocessor{
    public $version = "0.5";
    private $wire = null;
    private $fields = null;
    private $sanitizedFields = null;
    private $honeypotfields = null;
    private $mailreceiver = null; // specify CSV string or array for multiple addresses
    private $mailsender = null;
    private $mailreplyto = null;
    private $mailreplytofield = null;
    private $mailsubject = null;
    private $mailsendername = null;
    private $timestampduration = 86400;
    private $timestampfield = null;
    private $mulivalueseperator = " | ";
    private $missingfields = array();
    private $customfieldprefix = 'customfield--';

    /**
     * PwFormprocessor constructor.
     * @param $wire_instance ProcessWire wire object
     */
    function __construct($wire_instance){
        $this->wire = $wire_instance;

        // check if it looks like the ProcessWire wire object
        if(!is_object($this->wire) OR !is_object($this->wire->sanitizer)){
            die('No valid $wire object given');
        }
    }

    /**
     * Define your fields like this:
     * <code>
     * $fields = array(
     *    'fieldname_from_post' => array(
     *          'label' => 'Field Label',                                       // human readable label of the field (for email output)
     *          'sanitizer' => 'text',                                          // ProcessWire sanitizer name
     *          'required' => true,
     *          'fallback' => 'fallback value if not set',                      // optional
     *          'htmloptions' => 'nl2br fullwidth nohtmlentities',              // optional (one or more options separated by space)
     *          'errortext' => 'error message if field is required but not set' // optional
     *      ),
     *      'customfield--custom_field_name' => array(                          // custom field has to be prefixed with "customfield--"
     *           'label' => 'Field Label',                                      // NOTE: NO sanitizer applied, be careful!
     *           'value' => 'Custom Field Value',
     *           'htmloptions' => 'nl2br fullwidth nohtmlentities',             // optional (one or more options separated by space)
     *       ),
     * );
     * </code>
     *
     * @param array $fields
     */
    public function setFields(array $fields){
        $this->fields = $fields;
    }

    /**
     * @param array $fields
     */
    public function setHoneypotFields(array $fields){
        $this->honeypotfields = $fields;
    }

    /**
     * @param string $receiver
     */
    public function setMailReceiver(string $receiver){
        $this->mailreceiver = $receiver;
    }

    /**
     * @param string $sender
     */
    public function setMailSender(string $sender){
        $this->mailsender = $sender;
    }

    /**
     * @param string $sender
     */
    public function setMailReplyTo(string $replyto){
        $this->mailreplyto = $replyto;
    }

    /**
     * @param string $mailreplytofield
     * @return void
     */
    public function setMailReplyToField(string $mailreplytofield): void
    {
        $this->mailreplytofield = $mailreplytofield;
    }

    /**
     * @param string $subject
     */
    public function setMailSubject(string $subject){
        $this->mailsubject = $subject;
    }

    /**
     * @param string $name
     */
    public function setMailSenderName(string $name){
        $this->mailsendername = $name;
    }

    /**
     * @param int $timestampduration
     */
    public function setTimestampduration(int $timestampduration){
        $this->timestampduration = $timestampduration;
    }

    /**
     * @param int $timestampduration
     */
    public function setTimestampfield(string $timestampfield){
        $this->timestampfield = $timestampfield;
    }

    /**
     * @param string $mulivalueseperator
     */
    public function setMulivalueseperator(string $mulivalueseperator){
        $this->mulivalueseperator = $mulivalueseperator;
    }

    /**
     * @param string $customfieldprefix
     */
    public function setCustomfieldprefix(string $customfieldprefix): void
    {
        $this->customfieldprefix = $customfieldprefix;
    }



    /**
     * @return array
     */
    public function processForm(){
        // check if honeypot fields where populated
        if(!$this->checkHoneypotFields()){
            return array(
                'success' => false,
                'reason' => 'honeypot'
            );
        };

        // check timestamp
        if(!$this->checkTimestamp()){
            return array(
                'success' => false,
                'reason' => 'timestamp'
            );
        };

        // check required fields
        if(!$this->checkReqiuredFields()){
            return array(
                'success' => false,
                'reason' => 'required',
                'missingfields' => $this->getMissingFields($includeErrorTexts = true)
            );
        };

        // sanitize fields
        $this->sanitizeFields();

        // if we didn't return yet, everything seems to be fine
        return array(
            'success' => true,
            'reason' => 'passed',
            'sanitizedfields' => $this->getSanitizedFields()
        );
    }

    /**
     * @return array
     */
    public function processFormAndSend(){
        $processResult = $this->processForm();
        if(array_key_exists('success',$processResult) AND $processResult['success'] == true){
            // at this point we could merge some fields (stored in $processResult['sanitizedfields']) for the html generation
            // but for now we just pass the method the sanitized fields (wich contains labels and values)
            if($this->sendEmail($processResult['sanitizedfields'])){
                return array(
                    'success' => true,
                    'reason' => false
                );
            } else {
                return array(
                    'success' => false,
                    'reason' => 'mailnotsent'
                );
            }
        } elseif(array_key_exists('reason',$processResult)){
            $returndata = array(
                'success' => false,
                'reason' => $processResult['reason']
            );

            // add missing fields if any, including error texts
            $missingfields = $this->getMissingFields($includeErrorTexts = true);
            if(count($missingfields)){
                $returndata['missingfields'] = $missingfields;
            }

            return $returndata;
        }
        return array(
            'success' => false,
            'reason' => 'notspecified'
        );
    }

    /**
     * @return bool true if honeypot NOT filled
     */
    private function checkHoneypotFields(){
        foreach ($this->honeypotfields as $honeypot){
            if($this->wire->input->post($honeypot)){ return false;};
        }
        return true;
    }

    /**
     * @return bool true if timestamp not older than $this->timestampduration
     */
    private function checkTimestamp(){
        if($this->timestampfield){
            $timestamp = $this->wire->input->post($this->timestampfield);
            $duration = time()-$this->timestampduration;
            if($timestamp < $duration OR $timestamp > time()){
                return false;
            }
        }
        return true;
    }

    /**
     * @return bool true if ALL required fields are set
     */
    private function checkReqiuredFields(){
        foreach ($this->fields as $fieldname => $config){
            if(array_key_exists('required',$config) AND $config['required']==true){
                $value = $this->wire->input->post($fieldname);
                // if we have the field in post ...
                if($value){
                    // ... we check if there is a sanitizer to use
                    if(array_key_exists('sanitizer',$config) AND method_exists($this->wire->sanitizer, $config['sanitizer'])){
                        // ... if sanitizer results in empty string we claim the field as not valid
                        if($this->wire->sanitizer->{$config['sanitizer']}($value) ==""){
                            $this->missingfields[] = $fieldname;
                        }
                    }
                } else {
                    // if field is not in post ...
                    $this->missingfields[] = $fieldname;
                }
            }
        }
        // if we have missing fields, we return false
        if(count($this->getMissingFields())){
            return false;
        }

        // not exited yet, return true
        return true;
    }

    /**
     * @return void
     */
    private function sanitizeFields(){
        foreach ($this->fields as $fieldname => $config){

            // handling custom fields
            if($this->isCustomfield($fieldname)){
                $this->sanitizedFields[$fieldname]['value'] = $config['value'];
                $this->sanitizedFields[$fieldname]['label'] = $config['label'];
                if(array_key_exists('htmloptions',$config)){
                    $this->sanitizedFields[$fieldname]['htmloptions'] = $config['htmloptions'];
                }
                continue;
            }

            // handling regular fields
            $value = $this->wire->input->post($fieldname);
            if(array_key_exists('sanitizer',$config) AND method_exists($this->wire->sanitizer, $config['sanitizer'])){
                // check if the current field value is an array (multi value fields such as checkboxes and multi selects)
                if(is_array($value)){
                    $value = implode($this->mulivalueseperator,$value);
                }
                $value = $this->wire->sanitizer->{$config['sanitizer']}($value);
            }
            // if sanitizer results in empty string, we check if we have a fallback value set
            if($value =="" AND array_key_exists('fallback',$config)){
                $value = $config['fallback'];
            }
            $this->sanitizedFields[$fieldname]['value'] = $value;

            if(array_key_exists('label',$config)){
                $this->sanitizedFields[$fieldname]['label'] = $config['label'];
            } else {
                $this->sanitizedFields[$fieldname]['label'] = null;
            }

            // attach given htmloptions if any (processed later while preparing html email)
            if(array_key_exists('htmloptions',$config)){
                $this->sanitizedFields[$fieldname]['htmloptions'] = $config['htmloptions'];
            }
        }
    }

    /**
     * @param $fieldname
     * @return bool
     */
    private function isCustomfield($fieldname){
        if(str_starts_with($fieldname, $this->customfieldprefix)){
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    private function getSanitizedFields()
    {
        return $this->sanitizedFields;
    }


    /**
     * @param array $data
     * @return string
     */
    private function prepareHtmlValue(array $data){
        if(array_key_exists('htmloptions',$data)){
            $options = explode(" ", $data['htmloptions']);

            // only apply htmlentities() if NO 'nohtmlentities' option set
            // NOTE: htmlentities() is applied via ProcessWire sanitizer in the most cases,
            // so this option only works with no sanitizer specified in field settings
            if(!in_array('nohtmlentities',$options)){
                $value = htmlentities($data['value']);
            } else {
                $value = $data['value'];
            }

            // process options in order of configuration
            foreach ($options as $option){
                switch($option){
                    case 'nl2br':
                        $value = nl2br($value);
                        break;
                }
            }
            return $value;

        } else {
            return htmlentities($data['value']);
        }
    }


    /**
     * @param array $data
     * @return string
     */
    private function prepareHtmlRow(array $data){
        $row = "";
        $value = $this->prepareHtmlValue($data);
        // only add data which has a label defined
        if(array_key_exists('label',$data) AND $data['label'] AND $value!=""){

            // check if we have 'fullwidth' set in 'htmloptions'
            if(array_key_exists('htmloptions',$data) AND str_contains($data['htmloptions'],'fullwidth')){
                $row = "<tr>\n
                                <td colspan='2'>
                                <strong>".htmlentities($data['label'])."</strong><br><br>
                                {$value}
                                </td>
                            </tr>";
            } else {
                $row = "<tr>\n
                                <th>".htmlentities($data['label'])."</th>
                                <td>{$value}</td>
                            </tr>";
            }
        }

        return $row;
    }


    /**
     * @param array $sanitizedfields
     * @return false|string
     */
    private function buildEMailHTML(array $sanitizedfields){
        if(!is_array($sanitizedfields)){
            return false;
        }

        $message_rows = "";
        foreach ($sanitizedfields as $field => $data){
            $message_rows.=$this->prepareHtmlRow($data);
        }

        $message_header = "<style>
                        body{
                            font-family: 'Arial', sans-serif;
                        }
                        table{
                            width: 100%;
                        }    
                        th{
                            text-align: left;
                        }
                        th, td{
                            border-bottom: 1px solid #666;
                            padding: 0.5em;
                            vertical-align: top;
                        }    
                    </style>";

        $message = $message_header
            . "<h1>".htmlentities($this->mailsubject)."</h1>\n"
            . "<table>\n"
            . $message_rows
            . "</table>\n";

        return $message;
    }


    /**
     * @param array $sanitizedfields
     * @return false|string
     */
    private function buildEmailText(array $sanitizedfields){
        if(!is_array($sanitizedfields)){
            return false;
        }
        $message = strtoupper($this->mailsubject)."\n";
        $message.="================================================================================\n\n";
        foreach ($sanitizedfields as $field => $data){
            // only add data which has a label defined
            if(array_key_exists('label',$data) AND $data['label']){
                $message.=$data['label'].":\n";

                // if it is a custom field it may contain HTML markup, so we convert it to text
                if($this->isCustomfield($field)){
                    $message.=$this->wire->sanitizer->markupToText($data['value'])."\n";
                } else {
                    $message.=$data['value']."\n";
                }
                $message.="--------------------------------------------------------------------------------\n";
            }
        }
        return $message;
    }

    /**
     * @return array
     */
    public function getMissingFields($includeErrorTexts = false){
        if(!$includeErrorTexts){
            return $this->missingfields;
        }

        // if $includeErrorTexts is true, we return an array with fieldnames as keys and error texts as values
        $errorTexts = array();
        foreach ($this->missingfields as $fieldname){
            if(array_key_exists($fieldname,$this->fields)){
                if(array_key_exists('errortext',$this->fields[$fieldname])){
                    $errorTexts[$fieldname] = $this->fields[$fieldname]['errortext'];
                }
            }
        }
        return $errorTexts;
    }

    /**
     * @param array $sanitizedfields
     * @return bool
     */
    public function sendEmail(array $sanitizedfields){
        // generate HTML and Text for email
        $html = $this->buildEMailHTML($sanitizedfields);
        $text = $this->buildEMailText($sanitizedfields);

        // instantiate ProcessWire WireMail class
        $m = $this->wire->mail->new();

        // set data
        $m->to($this->mailreceiver);
        $m->subject($this->mailsubject);
        $m->bodyHTML($html);
        $m->body($text);

        // sender
        if($this->mailsendername){
            // with name ("John Dow <doe@example.com>")
            $from = $this->mailsendername. " <{$this->mailsender}>";
        } else {
            // without name ("doe@example.com")
            $from = $this->mailsender;
        }
        if($from){
            $m->from($from);
        }

        // check if we have a replyTo field set
        if($this->mailreplytofield){
            // get contents from post (if any), sanitize and set as replyTo
            $replyTo = $this->wire->input->post($this->mailreplytofield, 'email');
            if($replyTo){
                $this->setMailReplyTo($replyTo);
            }
        }

        // replyTo (set via setMailReplyTo() or setMailreplytofield())
        if($this->mailreplyto){
            $m->replyTo($this->mailreplyto);
        }


        // send
        if($m->send()){
            return true;
        } else {
            return false;
        }
    }

}