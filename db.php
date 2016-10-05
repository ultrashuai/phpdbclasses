<?PHP
// class for mysql interaction in PHP 5, writting by colin gremillion

	class database {
	
		private $conn;
		private $server;
		private $dbname;
		private $userPrefix;
		public $user;
		public $rows;
		public $result;
		public $results = array();
		public $firstRow;
		public $flipResults = array();
		public $lastQuery;
		public $queriesRun = array();
		public $lastInsertID;
		public $rowsAffected;
		
		public $error;
		public $errorString;
		private $errors = array(
			'query'=>'Query failed.'
		);
		
		private $allowedQueries = array(
			'sqlsel'=>array('select','show','describe'),
			'sqlins'=>array('select','show','describe','update','insert','delete')
		);
		
		private $allowedFunctions = array('date_format','date_add','month','year','coalesce');
		
		function __construct ($user='sqlsel',$db='newwest') {
			$this->server = ($_SERVER['SERVER_NAME']=='localhost') ? 'localhost:666' : 'localhost';
			
/* REPLACE WITH YOUR DB NAME AND USER PREFIX (FOR SHARED HOSTING) */
			$this->dbname = '';
			$this->userPrefix = '';
			
			$this->connect($user);
		}
		
		private function connect ($user) {
/*	REPLACE WITH YOUR DB LOGIN INFORMATION */
			$user = '';
			$pass = '';
			
			$this->user = $user;
			
			if ($this->conn) $this->disconnect();
			
			$this->conn = new mysqli($this->server, $this->userPrefix.$this->user, $pass);
			//$this->conn = mysqli_connect($this->server,$this->userPrefix.$this->user,$pass);
			if ($this->conn===false) {
				$this->conn = new mysqli($this->server, $this->userPrefix.$this->user, $pass);
				//$this->conn = mysqli_connect($this->server,$this->userPrefix.$this->user,$pass);
				if ($this->conn===false) $this->dbError();
			}
			mysqli_select_db($this->conn, $this->dbname);
		}
		
		public function query ($query,$storeResults=true) {
			$this->rows = NULL;
			$this->result = NULL;
			$this->results = array();
			$this->firstRow = NULL;
			$this->flipResults = array();
			$this->lastInsertID = NULL;
			$this->rowsAffected = NULL;
			
			preg_match("/^[\s\t\r\n]*(\w+) /",$query,$matches);
			if (count($matches)!=2) return $this->errorOut('query-notallowed',"Query regex failed: $query");
			$type = strtolower($matches[1]);
			if (!in_array($type,$this->allowedQueries[$this->user])) return $this->errorOut('query-notallowed');
			
			if ($type=='insert' || $type=='update' || $type=='delete') $storeResults = false;
			
			try {
				$this->result = mysqli_query($this->conn, $query);
			}
			catch (Exception $ex) {
				$this->error = 'query';
				$this->errorString = $ex->getMessage();
				return false;
			}
			$this->lastQuery = $query;
			if ($this->result===false) return $this->errorOut('query',mysqli_error($this->conn)."<br/>$query");
			$this->queriesRun[] = $query;
			
			if ($type=='insert') {
				$this->lastInsertID = @mysqli_insert_id($this->conn);
				return $this->lastInsertID;
			}
			elseif ($type=='update' || $type=='delete') $this->rowsAffected = @mysqli_affected_rows($this->conn);
			else {
				if ($storeResults) {
					while ($row=mysqli_fetch_array($this->result,MYSQL_ASSOC)) {
						if (!$this->firstRow) $this->firstRow = $row;
						$this->results[] = $row;
					}
				}
				$this->rows = mysqli_num_rows($this->result);
			}
			
			return $this->result;
		}
		
		public function flip () {
			if (!count($this->results)) return false;
			foreach ($this->results as $i=>$row) {
				foreach ($row as $f=>$v) {
					if (!isset($this->flipResults[$f])) $this->flipResults[$f] = array();
					$this->flipResults[$f][$i] = $v;
				}
			}
		}
		
		// for insert/update statements
		public function verifyFields ($table,$fvPairs) {
			if (!preg_match("/^[\w\d\-\_]+$/",$table)) return false;
			if (!is_array($fvPairs)) return false;
			if (!count($fvPairs)) return false;
			$q = $this->query("SELECT * FROM $table",false);
			$msg = "The following field failed validation: ";
			for ($i=0; $i<mysql_num_fields($q); $i++) {
				$fn = mysql_field_name($q,$i);
				$ft = mysql_field_type($q,$i);
				if (stripos(mysql_field_flags($q,$i),'not_null')!==false && !isset($fvPairs[$fn])) return $this->errorOut('fields-notnull',$msg.$fn);
				elseif (!isset($fvPairs[$fn])) continue;
				
				if ($ft=='int') {
					if (stripos(mysql_field_flags($q,$i),'not_null')===false && !$fvPairs[$fn] && $fvPairs[$fn]!==0 && $fvPairs[$fn]!=='0') return $this->errorOut('fields-notnull',$msg.$fn);
					if ($fvPairs[$fn] && !is_numeric($fvPairs[$fn])) return $this->errorOut('fields-valid',$msg.$fn);
					continue;
				}
				elseif ($ft=='real') {
					if (stripos(mysql_field_flags($q,$i),'not_null')===false && !$fvPairs[$fn] && $fvPairs[$fn]!==0 && $fvPairs[$fn]!=='0') return $this->errorOut('fields-notnull',$msg.$fn);
					if ($fvPairs[$fn] && !preg_match("/^\-?[\d\.\,]+$/",$fvPairs[$fn])) return $this->errorOut('fields-valid',$msg.$fn);
					continue;
				}
				elseif ($ft=='string') {
					if (stripos(mysql_field_flags($q,$i),'not_null')===false && !$fvPairs[$fn] && $fvPairs[$fn]!=='false' && $fvPairs[$fn]!==0 && $fvPairs[$fn]!=='0') return $this->errorOut('fields-notnull',$msg.$fn);
					if (strlen($fvPairs[$fn])>mysql_field_len($q,$i)) return $this->errorOut('fields-length',$msg.$fn);
				}
			}
		}
		
		public function format ($str,$nulls=true) {
			if ($nulls && ($str==='' || $str===false)) return 'NULL';
			else return "'".str_replace("'","''",stripslashes($str))."'";
		}
		
		public function switchUser () {
			if ($this->user=='sqlsel') $this->connect('sqlins');
			else $this->connect('sqlsel');
		}
		
		public function switchDB ($db) {
			mysqli_select_db($this->conn, $this->userPrefix.$db);
		}
		
		private function logError () {
			
		}
		
		private function dbError () {
			$this->logError();
			//header("Location: /dberror.php");
			exit;
		}
		
		private function errorOut ($str,$msg='') {
			$this->errorString = '';
			if (isset($this->errors[$str])) $this->errorString = $this->errors[$str];
			if ($msg) {
				if ($this->errorString) $this->errorString .= '<br/>';
				$this->errorString .= $msg;
			}
			echo $this->errorString;
			return false;
		}
		
		public function disconnect () {
			@mysqli_close($this->conn);
		}
	
	}
	
?>