<?php
//######################################################
//# File: sms_pdu_decode.php
//# 
//# PDU decoding library. Decodes PDU message of SMS
//#
//# Example:
//#            $sms_pdu_decode = new sms_pdu_decode();
//#            $sms_pdu_decode->pdu = trim($sms_hdr[1]);
//#            $sms_pdu_decode->decode();
//#            if ($sms_pdu_decode->pduParseError!="") {
//#                echo "PDU decode error::",$sms_pdu_decode->pduParseError,"\n";
//#                continue;
//#            }
//#            $message = $sms_pdu_decode->user_data;
//#            $fromnum = $sms_pdu_decode->sender_number;
//#
//######################################################

error_reporting(E_ALL);

class sms_pdu_decode {

	const NUMBER_TYPE_UNKNOWN = 1;
	const NUMBER_TYPE_INTERNATIONAL = 2;
	const NUMBER_TYPE_NATIONAL = 3;
	const NUMBER_TYPE_NETWORK = 4;
	const NUMBER_TYPE_SUBSCRIBER = 5;
	const NUMBER_TYPE_ALPHANUMERIC = 6;
	const NUMBER_TYPE_ABBREVIATED = 7;
	const NUMBER_TYPE_RESERVED = 8;

	const NUMBER_PLAN_UNKNOWN = 1;
	const NUMBER_PLAN_ISDN = 2;
	const NUMBER_PLAN_DATA = 3;
	const NUMBER_PLAN_TELEX = 4;
	const NUMBER_PLAN_NATIONAL = 5;
	const NUMBER_PLAN_PRIVATE = 6;
	const NUMBER_PLAN_ERMES = 7;
	const NUMBER_PLAN_RESERVED = 8;

	const MESSAGE_TEXT_UNCOMPRESSED = 0;
	const MESSAGE_TEXT_COMPRESSED = 1;

	const MESSAGE_ALPHABET_DEFAULT = 1;
	const MESSAGE_ALPHABET_8BITDATA = 2;
	const MESSAGE_ALPHABET_UCS2 = 3;
	const MESSAGE_ALPHABET_RESERVED = 4;

	const MESSAGE_CLASS_IMMEDIATE_DISPLAY = 0;
	const MESSAGE_CLASS_ME_SPECIFIC = 1;
	const MESSAGE_CLASS_SIM_SPECIFIC = 2;
	const MESSAGE_CLASS_TE_SPECIFIC = 3;

	public $pdu;
	
	private $retr_next_char_pos = 0;

	private $smsc_len;

	public $smsc_number;
	public $smsc_number_type;
	public $smsc_number_plan;

	private $sender_number_len;

	public $sender_number;
	public $sender_number_type;
	public $sender_number_plan;

	public $message_compression;
	public $message_alphabet;
	public $message_class;
	

	public $smsc_timestamp;

	private $user_data_len;
	public $user_data;
	public $user_data_header;
	public $isMultipart;
	public $multipartId;	
	public $multipartNumber;
	public $multipartTotal;
	
	public $pduParseError;

	public $isVerbose;

	public function decode() {
	
		$this->pduParseError = "";
		$this->isMultipart = false;
		try {
			$this->smsc_len(); //Length of the SMSC information
			$this->smsc_number_type();		//Type of SMSC address
			$this->smsc_number();		//Service center number
			$this->message_type();		//Message Type indicator
			$this->sender_number_len();		//Sender number length 		
			$this->sender_number_type();		//Sender number type 
			$this->sender_number();		//Sender number 			
			$this->protocol_identifier();		//Protocol identifier
			$this->data_coding_scheme();		//Data coding scheme
			$this->smsc_timstamp();		//SMSC Timestamp
			$this->user_data_length();		//Userdata length
			$this->user_data();		//Userdata
		} catch (Exception $e) {
			echo 'Exception: ',  $e->getMessage(), "\n";
			echo ' Line: ', $e->getLine();
			return 1;		
		}
	}

	private function decode7bit($input) {
		$result = "";
	    $data = str_split(pack('H*', $input));
    	$mask = 0xFF;
    	$shift = 0;
    	$carry = 0;
    	foreach ($data as $char) {
            if ($shift == 7) {
                    $result .= chr($carry);
                    $carry = 0;
                    $shift = 0;
            }

            $a = ($mask >> ($shift+1)) & 0xFF;
            $b = $a ^ 0xFF;

            $digit = ($carry) | ((ord($char) & $a) << ($shift)) & 0xFF;
            $carry = (ord($char) & $b) >> (7-$shift);
            $result .= chr($digit);
            $shift++;
    	}
    	if ($carry) $result .= chr($carry);
		return $result;
	}
	
	//Length of the SMSC information
	private function smsc_len() {
		$len = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "len::==$len\n";
		
		$this->smsc_len = self::number_len($len);
		if ($this->isVerbose) echo "this->smsc_len::==$this->smsc_len\n";
	}

	//SMSC number type
	private function smsc_number_type() {
		$type = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "type::==$type\n";

		$type_info = self::number_type($type);

		$this->smsc_number_type = $type_info['type'];
		if ($this->isVerbose) echo "this->smsc_number_type::== $this->smsc_number_type\n";

		$this->smsc_number_plan = $type_info['plan'];
		if ($this->isVerbose) echo "this->smsc_number_plan::== $this->smsc_number_plan\n";
	}

	//SMSC number
	private function smsc_number() {
		$length = ($this->smsc_len * 2) - 2;
		if ($this->isVerbose)  echo "length::==$length\n";
		
		$number = $this->retrieve_next_char($length);
		if ($this->isVerbose)  echo "number::==$number\n";
		
		$this->smsc_number = $this->number($number);
		if ($this->isVerbose)  echo "this->smsc_number::==$this->smsc_number\n";
	}

	//message_type
	private function message_type() {

		$octet = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "octet::==$octet\n";
		
		$tp_mti_byte_dec = hexdec($octet);
		if ($this->isVerbose) echo "tp_mti_byte_dec::==$tp_mti_byte_dec\n";
		 
		$tp_mti_byte_bin = str_pad(decbin($tp_mti_byte_dec), 8, '0', STR_PAD_LEFT);
		if ($this->isVerbose) echo "tp_mti_byte_bin::==$tp_mti_byte_bin\n";

		if (!preg_match('/^(\d{1})(\d{1})(\d{1})(\d{1})(\d{1})(\d{1})(\d{2})$/', $tp_mti_byte_bin, $matches)) {
			throw new Exception("TP-MTI match failed \"{$tp_mti_byte_bin}\"");  
		}
		
		$this->user_data_header = $matches[2];
		
	}

	//First octet
	private function first_octet() {
		$first_octet = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "first_octet::==$first_octet\n";
	}

	//Sender number length
	private function sender_number_len() {
		$len = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "len::==$len\n";
		
		$this->sender_number_len = self::number_len($len);
		if ($this->isVerbose) echo "this->sender_number_len::==$this->sender_number_len\n";
	}

	//Sender number type
	private function sender_number_type() {
		$type = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "type::==$type\n";
		$type_info = self::number_type($type);
		
		$this->sender_number_type = $type_info['type'];
		if ($this->isVerbose) echo "this->sender_number_type::=$this->sender_number_type\n";
		
		$this->sender_number_plan = $type_info['plan'];
		if ($this->isVerbose) echo "this->sender_number_plan::=$this->sender_number_plan\n";
	}

	//Sender number
	private function sender_number() {
		switch ($this->sender_number_type) {
			case self::NUMBER_TYPE_UNKNOWN:
			case self::NUMBER_TYPE_INTERNATIONAL:
                    $number = $this->retrieve_next_char(round($this->sender_number_len / 2) * 2);
                    $this->sender_number = $this->number($number);
					break;
			case self::NUMBER_TYPE_ALPHANUMERIC:
					$number = $this->retrieve_next_char(round($this->sender_number_len / 2) * 2);
					$this->sender_number = $this->decode7bit($number);
					break;
			default:
					$number = $this->retrieve_next_char($this->sender_number_len + 1);
					$this->sender_number = $this->number($number);
					break;
		}
	}
	//Protocol identifier
	private function protocol_identifier() 
	{

		$protocol_identifier = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "protocol_identifier::==$protocol_identifier\n";
		if ($protocol_identifier != '00') {
			throw new Exception("Protocol identifier \"{$protocol_identifier}\" not supported");
		}

	}

	//Data coding scheme
	private function data_coding_scheme() {

		//http://web.archive.org/web/20120714175243/http://dreamfabric.com/sms/dcs.html

		$data_coding_scheme = $this->retrieve_next_char(2);
		if ($this->isVerbose) echo "data_coding_scheme::==$data_coding_scheme\n";
		
		$data_coding_scheme_dec = hexdec($data_coding_scheme);
		if ($this->isVerbose) echo "data_coding_scheme_dec::==$data_coding_scheme_dec\n";

		$data_coding_scheme_bin = str_pad(decbin($data_coding_scheme_dec), 8, '0', STR_PAD_LEFT);
		if ($this->isVerbose) echo "data_coding_scheme_bin::==$data_coding_scheme_bin\n";

		//If "General Data Coding" scheme
		if (preg_match('/^00(\d{1})(\d{1})(\d{2})(\d{2})$/', $data_coding_scheme_bin, $matches)) {

			$compression_code = $matches[1];
			if ($this->isVerbose) echo "compression_code::==$compression_code\n";
			
			$alphabet_code = $matches[3];
			if ($this->isVerbose) echo "alphabet_code::==$alphabet_code \n";
			
			$class_code = $matches[4];
			if ($this->isVerbose) echo "class_code::==$class_code\n";
			
			//Compression			    
			$this->message_compression = ($compression_code == '1') ? self::MESSAGE_TEXT_COMPRESSED : self::MESSAGE_TEXT_UNCOMPRESSED;

			//Alphabet
			$alphabet_resolve = array(
				'00' => self::MESSAGE_ALPHABET_DEFAULT,
				'01' => self::MESSAGE_ALPHABET_8BITDATA,
				'10' => self::MESSAGE_ALPHABET_UCS2,
				'11' => self::MESSAGE_ALPHABET_RESERVED,
			);

			if (!isset($alphabet_resolve[$alphabet_code])) {
				throw new Exception("Message alphabet \"{$alphabet_code}\" not recognised");
			}

			$this->message_alphabet = $alphabet_resolve[$alphabet_code];
			if ($this->isVerbose) echo "this->message_alphabet::==$this->message_alphabet\n";
			
			//Class
			$class_resolve = array(
				'00' => self::MESSAGE_CLASS_IMMEDIATE_DISPLAY,
				'01' => self::MESSAGE_CLASS_ME_SPECIFIC,
				'10' => self::MESSAGE_CLASS_SIM_SPECIFIC,
				'11' => self::MESSAGE_CLASS_TE_SPECIFIC,
			);

			if (!isset($class_resolve[$class_code])) {
				throw new Exception("Message class \"{$class_code}\" not recognised");
			}

			$this->message_class = $class_resolve[$class_code];
			if ($this->isVerbose) echo "this->message_class::==$this->message_class\n";
		} else {
			throw new Exception("Data coding scheme not supported \"{$data_coding_scheme_bin}\"");
		}

	}

	//SMSC Timestamp
	private function smsc_timstamp() {
		$timestamp = "";
		$sep_str = str_split("// ::. ");
		$sms_timestamp = $this->retrieve_next_char(14);
		$sms_timestamp_chunks = str_split($sms_timestamp, 2);
		if ($this->isVerbose) echo "sms_timestamp:==$sms_timestamp\n";
		$i=0;
		foreach ($sms_timestamp_chunks as $chunk) {
			$chunk_rev = strrev($chunk);
			$timestamp .= $chunk_rev . $sep_str[$i++];
		}
		$this->smsc_timstamp = rtrim($timestamp, 'F');
		if ($this->isVerbose) echo "this->smsc_timstamp:==$this->smsc_timstamp\n";
	}

	//User Data Length
	private function user_data_length() {
		if ($this->message_alphabet == self::MESSAGE_ALPHABET_DEFAULT) {
			$user_data_len = $this->retrieve_next_char(2);
		} else {
			$user_data_len = $this->retrieve_next_char(2);
		}
		if ($this->isVerbose) echo "user_data_len::==$user_data_len\n";
		
		$user_data_len_dec = hexdec($user_data_len);
		if ($this->isVerbose) echo "user_data_len_dec::==$user_data_len_dec\n";
		
		if (!( ($user_data_len_dec >= 0) && ($user_data_len_dec <= 160) )) {
			throw new Exception("Message length \"{$user_data_len}\" invalid");
		}

		$this->user_data_len = $user_data_len_dec;
	}

	//User Data
	private function user_data() {
		if ($this->user_data_header) {
    		    
    		    $udhl=$this->retrieve_next_char(2);
                    if ($this->isVerbose) echo "udhl::==$udhl\n";
                    
                    $udhl_dec = hexdec($udhl);
                    $udhi=$this->retrieve_next_char($udhl_dec*2);
                    if ($this->isVerbose) echo "udhi::==$udhi\n";
                    
                    $udhi_arr = str_split($udhi,2);
		    if ($udhi_arr[3]>1) {
			$this->isMultipart = true;
                	$this->multipartId = $udhi_arr[2];
                        $this->multipartNumber = $udhi_arr[4];
                        $this->multipartTotal = $udhi_arr[3];
                    }
    		    $this->user_data_len=$this->user_data_len-$udhl_dec;
    		};
    		
		$user_data = $this->retrieve_next_char($this->user_data_len * 2);
		if ($this->isVerbose) echo "user_data::==$user_data\n";
		
		//Check no compression
		if ($this->message_compression == self::MESSAGE_TEXT_COMPRESSED) {
			throw new Exception('Compression is not supported');
		}

		$user_data_text = '';

		if ($this->message_alphabet == self::MESSAGE_ALPHABET_DEFAULT) {
			//If 7 bit alphabet
			$user_data_text = $this->decode7bit($user_data);
		}
		elseif ($this->message_alphabet == self::MESSAGE_ALPHABET_8BITDATA) {
			//If 8 bit alphabet
			$user_data_octets = str_split($user_data, 2);
			foreach ($user_data_octets as $octet) {
				$decimal_char = hexdec($octet);
				$user_data_text .= chr($decimal_char);
			}
		} elseif ($this->message_alphabet == self::MESSAGE_ALPHABET_UCS2) {
		    $user_data = preg_replace('/\s+/', '',$user_data);
		    $user_data_text = pack("H*",$user_data);
		    $user_data_text = mb_convert_encoding($user_data_text,'UTF-8','UCS-2');		        
		}
		else
		{
			throw new Exception("Message alphabet \"{$this->message_alphabet}\" not supported");
		}
		$this->user_data = $user_data_text;
	}

	//Retrieve characters from PDU data
	private function retrieve_next_char($length) {

		$characters = substr($this->pdu, $this->retr_next_char_pos, $length);
		$this->retr_next_char_pos = $this->retr_next_char_pos + $length;

		$characters_len = strlen($characters);

		if (($characters_len != $length) && ($this->isVerbose)) {
		//	throw new Exception("Expected length \"{$length}\"does not match available data length \"{$characters_len}\"") 
		}

		return $characters;

	}

	//Number length
	static private function number_len($len) {
		$len_dec = hexdec($len); 
		if (!( ($len_dec >= 0) && ($len_dec < 50) )) throw new Exception("Number length \"{$smsc_len_dec}\" invalid");
		return $len_dec;		
	}

	//Number type
	static private function number_type($type) {
		//http://web.archive.org/web/20120714175848/http://dreamfabric.com/sms/type_of_address.html
		$type_dec = hexdec($type);
		$type_bin = decbin($type_dec);

		if (!preg_match('/^\d{1}(\d{3})(\d{4})$/', $type_bin, $matches)) {
			throw new Exception("Number type match failed \"{$type_bin}\"");
		}


		$type_code = $matches[1];
		$plan_code = $matches[2];

		$type_resolve = array(
			'000' => self::NUMBER_TYPE_UNKNOWN,
			'001' => self::NUMBER_TYPE_INTERNATIONAL,
			'010' => self::NUMBER_TYPE_NATIONAL,
			'011' => self::NUMBER_TYPE_NETWORK,
			'100' => self::NUMBER_TYPE_SUBSCRIBER,
			'101' => self::NUMBER_TYPE_ALPHANUMERIC,
			'110' => self::NUMBER_TYPE_ABBREVIATED,
			'111' => self::NUMBER_TYPE_RESERVED,
		);

		if (!isset($type_resolve[$type_code])) {
			throw new Exception("Number type \"{$type_code}\" not recognised");
//			$this->pduParseError = "Number type \"{$type_code}\" not recognised";
                        return;			
		}

		$plan_resolve = array(
			'0000' => self::NUMBER_PLAN_UNKNOWN,
			'0001' => self::NUMBER_PLAN_ISDN,
			'0011' => self::NUMBER_PLAN_DATA,
			'0100' => self::NUMBER_PLAN_TELEX,
			'1000' => self::NUMBER_PLAN_NATIONAL,
			'1001' => self::NUMBER_PLAN_PRIVATE,
			'1010' => self::NUMBER_PLAN_ERMES,
			'1111' => self::NUMBER_PLAN_RESERVED,
		);

		if (!isset($plan_resolve[$plan_code])) {
			throw new Exception("Number plan \"{$plan_code}\" not recognised");
		}

		if ($type_code == '101') {
			if ($plan_resolve[$plan_code] != self::NUMBER_PLAN_UNKNOWN) {
				throw new Exception("Number plan (\"{$plan_code}\") must be 'unknown' for specified number type \"{$type_code}\"");
			}
		}

		$return = array(
			'type' => $type_resolve[$type_code],
			'plan' => $plan_resolve[$plan_code],
		);

		return $return;

	}

	//Number
	static private function number($number) {

		//Phone Number
		$phone_number = '';
		$telephone_chunks = str_split($number, 2);
		foreach ($telephone_chunks as $chunk) {
			$chunk_rev = strrev($chunk);
			$phone_number .= $chunk_rev;
		}

		$phone_number = rtrim($phone_number, 'F');

		return $phone_number;

	}

}

?>

