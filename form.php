<?PHP

	class form {
	
		
		var $phoneFormat = 'xxx-xxx-xxxx';
		
		
		var $db;
		var $error;
		var $fields;
		var $population;
		var $js;
		var $html;
		
		var $formName;
		var $formTitle;
		var $action = __SELF;
		var $method = 'post';
		var $enctype;
		var $width;
		var $submitName = array('submit'=>'Submit');
		var $cancelAction = 'reset';
		var $jsCheck = 'checkThisForm';
		
		var $res = array(
			'ssnRe'=>'/^\d{3}\-\d{2}\-\d{4}$/','zipRe'=>'/^\d{5}$/','zip4Re'=>'/^\d{5}\-\d{4}$/',
			'postalRe'=>'','moneyRe'=>'/^[\d\,]*\d\.\d{2}$/','textRe'=>'/\w/','passwordRe'=>'/^[\w\d\-\+!@#\$\%\^\&\*\;\:\?\.\,]+$/',
			'numRe'=>'/^\d+$/','urlRe'=>'/^https?\:\/\/[\w\d\_\/\.]+$/','decimalRe'=>'/^\d+(\.\d+)?$/',
			'phoneRe'=>'/^\(?\d{3}\)?( |\-)?\d{3}( |\-)\d{4}$/'
		);
		
		var $fieldKeys = array('type','name','text','req','size','max','check','note');
		var $fieldDefinitions = array(
			'number'=>array('text','num','Number',false,'12','11','numRe'),
			'decimal'=>array('text','decimal','Decimal',false,'15','14','decimalRe'),
			'ssn'=>array('text','ssn','SSN',false,'12','11','ssnRe'),
			// zip is for US only
			'zip'=>array('text','zip','Zip',false,'7','5','zipRe'),
			// zip4 is for zip+4
			'zip4'=>array('text','zip','Zip',false,'12','10','zip4Re'),
			// postal is for US and Canada
			'postal'=>array('text','postal','Postal Code',false,'8','7','postalRe'),
			// url
			'url'=>array('text','url','URL',false,'50','255','urlRe'),
			'phone'=>array('text','','',false,'5','5','phoneRe'),
			'money'=>array('text','','',false,'13','11','moneyRe'),
			'text'=>array('text','','',false,'50','255','textRe'),
			'radio'=>array('radio','','',false,array()),
			'hidden'=>array('hidden','','',false),
			'password'=>array('password','drowssap','Password',true,'35','70','passwordRe'),
			// button: type, action, value
			//'button'=>array('button',$_SERVER['PHP_SELF'],'Go'),
			// select: type, name, text, required, options array, 
			'select'=>array('select','','',false,array()),
			// textarea: type, name, text, required, array(rows,cols), max
			'textarea'=>array('textarea','','',false,array(5,65),''),
			// regular text: type, name, text, required, size, max
			'text'=>array('text','','',false,'50','255'),
			// datetime: type, name, text, required, date format
			'datetime'=>array('text','','',false,'mm-dd-yyyy hh:mi am')
		);
		
		function __construct ($db) {
			$this->db = $db;
			$this->fieldDefinitions['phone'][4] = strlen($this->phoneFormat)+2;
			$this->fieldDefinitions['phone'][5] = strlen($this->phoneFormat);
		}
		
		function setName ($name) {
			$this->formName = $name;
		}
		
		function setAction ($action) {
			$this->action = $action;
		}
		
		function setMethod ($method) {
			$this->method = $method;
		}
		
		function setEnctype ($type) {
			$this->enctype = $type;
		}
		
		function setWidth ($w) {
			if (!is_numeric($w)) return false;
			$this->width = $w;
		}
		
		function setCancelAction ($action) {
			$this->cancelAction = $action;
		}
		
		function addField ($type,$name,$text,$req=false,$size=false,$max=false,$check=false,$note=false) {
			if (!isset($this->fieldDefinitions[$type])) return false;
			if ($name && !preg_match("/^\w[\w\d\-\_]+$/",$name)) return false;
			// verify data types of arguments. if they don't match, set it to the default for the field type
			foreach (func_get_args() as $i=>$arg) {
				if ($i && gettype($arg)!=gettype($this->fieldDefinitions[$type][$i])) $$this->fieldKeys[$i] = $this->fieldDefinitions[$type][$i];
			}
			if ($this->fieldDefinitions[$type][0]=='text' || $this->fieldDefinitions[$type][0]=='password') {
				if (!numberMatch($size)) $size = $this->fieldDefinitions[$type][3];
				if (!numberMatch($max)) $size = $this->fieldDefinitions[$type][4];
			}
			
			$arr = array();
			foreach ($this->fieldKeys as $i=>$key) {
				if (!isset($$key)) $arr[$key] = $this->fieldDefinitions[$type][$i];
				$arr[$key] = $$key;
			}
			
			// notes
			$arr['note'] = '';
			if ($type=='ssn') $arr['note'] = 'Please use xxx-xx-xxxx format.';
			elseif ($type=='zip') $arr['note'] = 'Five numbers only.';
			
			$arr['type'] = $this->fieldDefinitions[$type][0];
			
			$this->fields[$name] = $arr;
			return $arr;
		}
		
		function renderField ($field) {
			$val = '';
			if (isset($field['name'])) {
				if (isset($_POST[$field['name']])) $val = postFormat($_POST[$field['name']]);
				elseif (isset($this->population[$field['name']])) $val = $this->population[$field['name']];
			}
			
			$r = '	<div class="';
			if (!$field['req']) $r .= 'no';
			$r .= 'req"><div class="field">'.$field['text'].'</div><div class="dynamic">';
			if (($field['type']=='radio' || $field['type']=='checkbox') && isset($field['size']) && is_array($field['size'])) {
				foreach ($field['size'] as $v=>$t) {
					$r .= '<input type="'.$field['type'].'" name="'.$field['name'];
					if ($field['type']=='checkbox') $r .= '[]';
					$r .= "\" value=\"$v\"";
					if ((is_array($val) && in_array($v,$val)) || $val==$v) $r .= ' checked';
					$r .= "/> $t<br/>\n";
				}
			}
			elseif ($field['type']=='select' && is_array($field['size'])) {
				$r .= '<select name="'.$field['name'].'"';
				if ($field['max']) $r .= ' onChange="'.str_replace('[this]','this',$field['max']).'"';
				$r .= ">\n";
				foreach ($field['size'] as $v=>$t) {
					$r .= "			<option value=\"$v\"";
					if ($val==$v) $r .= ' selected';
					$r .= ">$t</option>\n";
				}
				$r .= "		</select>\n";
			}
			
			elseif ($field['type']=='datetime') {
				$i = 0;
				$len = strlen($field['size']);
				$valDate = ($val && preg_match("/^\d{1,4}(\-|\/)?\d{1,2}(\-|\/)?\d{1,4}( \d{1,2}\:\d{1,2}/)?/",$val)) ? strtotime($val) : time();
				while ($i<$len) {
					if (substr($field['size'],$i,4)=='yyyy') {
						$r .= '<select name="year_'.$field['name'].'" onChange="setDate(this);">'."\n";
						$yearSpan = ($field['max'] && preg_match("/^\d{4}\-\d{4}$/",$field['max'])) ? explode('-',$field['max']) : array(date("Y"),date("Y")+5);
						for ($y=$yearSpan[0]; $y<=$yearSpan[1]; $y++) {
							$r .= "	<option value=\"$y\"";
							if (date("Y",$valDate)==$y) $r .= ' selected';
							$r .= ">$y</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+4;
					}
					
					elseif (substr($field['size'],$i,2)=='mm') {
						$r .= '<select name="month_'.$field['name'].'" onChange="setDate(this);">'."\n";
						for ($m=1; $m<=12; $m++) {
							$r .= "	<option value=\"$m\"";
							if (date("n",$valDate)==$m) $r .= ' selected';
							$r .= ">$m</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+2;
					}
					
					elseif (substr($field['size'],$i,3)=='mmm') {
						$r .= '<select name="month_'.$field['name'].'" onChange="setDate(this);">'."\n";
						for ($m=1; $m<=12; $m++) {
							$r .= "	<option value=\"$m\"";
							if (date("n",$valDate)==$m) $r .= ' selected';
							$r .= '>'.date("M",mktime(0,0,0,$m,1,2005))."</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+3;
					}
					
					elseif (substr($field['size'],$i,4)=='mmmm') {
						$r .= '<select name="month_'.$field['name'].'" onChange="setDate(this);">'."\n";
						for ($m=1; $m<=12; $m++) {
							$r .= "	<option value=\"$m\"";
							if (date("n",$valDate)==$m) $r .= ' selected';
							$r .= '>'.date("F",mktime(0,0,0,$m,1,2005))."</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+4;
					}
					
					elseif (substr($field['size'],$i,2)=='dd') {
						$r .= '<select name="day_'.$field['name']."\">\n";
						for ($d=1; $d<=date("t",$valDate); $d++) {
							$r .= "	<option value=\"$d\"";
							if (date("j",$valDate)==$d) $r .= ' selected';
							$r .= ">$d</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+2;
					}
					
					elseif (substr($field['size'],$i,2)=='hh') {
						$r .= '<select name="hour_'.$field['name']."\">\n";
						for ($h=1; $h<=12; $h++) {
							$r .= "	<option value=\"$h\"";
							if (date("g",$valDate)==$h) $r .= ' selected';
							$r .= ">$h</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+2;
					}
					
					elseif (substr($field['size'],$i,2)=='mm') {
						$r .= '<select name="min_'.$field['name']."\">\n";
						for ($m=0; $m<4; $m++) {
							$r .= '	<option value="'.($m*15).'"';
							if (date('i',$valDate)==($m*15)) $r .= ' selected';
							$r .= '>'.($m*15)."</option>\n";
						}
						$r .= "</select>\n";
						$i = $i+2;
					}
					
					elseif (substr($field['size'],$i,2)=='am') {
						$r .= '<select name="ampm_'.$field['name']."\">\n";
						$r .= '	<option value="am"';
						if (date('a',$valDate)=='am') $r .= ' selected';
						$r .= ">am</option>\n";
						$r .= '	<option value="pm"';
						if (date('a',$valDate)=='pm') $r .= ' selected';
						$r .= ">pm</option>\n</select>\n";
						$i = $i+2;
					}
					
					else $i++;
				}
			}
			
			else {
				if (in_array($field['type'],array('text','hidden','password','button','checkbox'))) $r .= '<input type="'.$field['type'].'"';
				elseif ($field['type']=='textarea') $r .= '<textarea';
				else return false;
				$r .= ' name="'.$field['name'].'"';
				if ($field['type']=='text' && $val) $r .= " value=\"$val\"";
				if ($field['size']) $r .= ' size="'.$field['size'].'"';
				if ($field['max']) {
					$r .= ' maxlength="'.$field['max'].'"';
					if ($field['type']=='textarea') $r .= ' onKeyDown="textAreaChars(this);"';
				}
				if ($field['check']) {
					if (in_array($field['type'],array('text','password','textarea'))) {
						$r .= ' onChange="if (!'.$field['check'].".test(this.value)) {alert('Invalid formatting.'); this.value=this.defaultValue;}\"";
					}
				}
				
				if (in_array($field['type'],array('text','hidden','password','button','checkbox'))) $r .= '/';
				$r .= ">";
				if ($field['type']=='textarea') {
					$r .= $val;
					$r .= "</textarea>\n";
				}
			}
			
			if ($field['note']) $r .= '<br/><span class="note">'.$field['note'].'</span>';
			
			$r .= "		</div></div>\n";
			
			return $r;
			
		}
				
		function submitName ($key,$text) {
			if ($key) $this->submitName[$key] = $text;
		}
		function resetSubmit () {
			$this->submitName = array();
		}
		
		function renderJavaScript ($includeTags=false) {
			if (!$this->fields) return false;
			$this->js = '';
			if ($includeTags) $this->js .= '	<script language="JavaScript" type="text/javascript">'."\n";
			foreach ($this->res as $re=>$pattern) {
				$this->js .= "		var $re = $pattern;\n";
			}
			$this->js .= "		function checkThisForm (formObj) {
			var alerts,chkd,div;
			var alerts = '';
			with (formObj) {\n";
			
			$tabs = '			';
			foreach ($this->fields as $i=>$fld) {
				if (!$fld['req']) continue;
				if ($fld['type']=='text' || $fld['type']=='textarea') $this->js .= "$tabs if (!".$fld['name'].".value) alerts += addFormError(".$fld['name'].");\n";
				elseif ($fld['type']=='radio' || $fld['type']=='checkbox') {
					$this->js .= "$tabs ischecked = false;\n$tabs";
					if ($fld['type']=='radio') $this->js .= 'var e = '.$field['name']."\n$tabs";
					else $this->js .= "var e = document.getElementsByName('".$field['name']."');\n$tabs";
					$this->js .= "for (var i=0; i<e.length; i++) {\n$tabs";
					$this->js .= "	if (e[i].checked) {\n$tabs		ischecked = true;\n$tabs		break;\n$tabs";
					$this->js .= "	}\n$tabs if (!ischecked) addFormError(".$fld['name'].");\n";
					$this->js .= "		}\n";
				}
				elseif ($fld['type']=='select' && is_array($fld['size']) && count($fld['size'])) {
					
				}
			}
			$this->js .= "
			}
		}\n";
			
			if ($this->method=='ajax') {
				$this->js .= '		function sendForm (formObj) {
				var aj = new ajax("'.$this->action.'");
				aj.doneFunction = function () {alert("Posted!"); document.getElementById("'.$this->formName.'").parentNode.style.display="none";};
				aj.method = "post";
				aj.connect();
				aj.process();
			}'."\n";
			}
			
			
			if ($includeTags) $this->js .= "	</script>\n";
			
			return $this->js;
		}
		
		function renderForm () {
			if (!$this->fields) return false;
			$this->html = '';
			if ($this->width) $this->html .= "	<div style=\"width:$this->width;\">\n";
			$this->html .= '	<form';
			if ($this->formName) $this->html .= ' name="'.$this->formName.'"';
			$this->html .= ($this->method=='ajax') ? ' action="'.$_SERVER['PHP_SELF'].'" method="post"' : " action=\"$this->action\" method=\"$this->method\"";
			if ($this->enctype) $this->html .= ' enctype="'.$this->enctype.'"';
			if ($this->method=='ajax') $this->html .= ' onSubmit="return false;">'."\n";
			else $this->html .= " onSubmit=\"return $this->jsCheck(this);\">\n";
			$this->html .= '	<fieldset>';
			if ($this->formTitle) $this->html .= "<legend>$this->formTitle</legend>\n";
			foreach ($this->fields as $field) {
				$this->html .= $this->renderField($field);
			}
			$this->html .= '		<div class="submitter">';
			foreach ($this->submitName as $key=>$txt) {
				$this->html .= '<input type="';
				if ($this->method=='ajax') $this->html .= 'button" onSubmit=""';
				else $this->html .= 'submit';
				$this->html .= "\" name=\"$key\" value=\"$txt\"";
				if ($key=='delete') $this->html .= ' class="deleteButton"';
				$this->html .= "/> &nbsp; ";
			}
			$this->html .= '<input type="';
			if ($this->cancelAction=='reset') $this->html .= 'reset" value="Reset"';
			else $this->html .= 'button" value="Cancel" onClick="window.location.href=\''.$this->cancelAction."';\"";
			$this->html .= "/></div>\n";
			$this->html .= "	</fieldset>\n	</form>\n";
			if ($this->width) $this->html .= "	</div>\n";
			return $this->html;
		}
		
		
		
		function checkForm ($post) {
			if (!$this->fields) {
				$this->error = 'No fields.';
				return false;
			}
			foreach ($this->fields as $f) {
				$err = '';
				if (!$f['req']) continue;
				if (!isset($post[$f['name']]) || (isset($post[$f['name']]) && !$post[$f['name']])) $err = "'".$f['text']."' is a required field.";
				if ($this->error) $this->error .= "<br/>$err\n";
			}
			return (!$this->error);
		}
	
	}

?>