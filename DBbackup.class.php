<?php
/**
 * MysqlBackuperInPHP
 *
 * @author Array-Huang <hyw125@gmail.com>
 * @copyright All for FREE. 2012/07/17
 * @access public
 */
final Class DBbackup
{
	//DB object
	private $db;
	//default DB backup file path like './DBbackup/' (the last '/' is required).The default value is './'.
	private $DefaultPath; 
	//DB name
	private $DBname;
	//DB server name
	private $servername;
	//user account
	private $username;

/**
 * connect to DB and Data initialization
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @param	$servername string	DB server name(if the port is not 3306,please add it) like '204.153.5.10:2234'
 * @param	$username string
 * @param	$password string
 * @param	$DBname   string
 * @param   $DefaultPath string DB backup file path like './DBbackup/' (the last '/' is required).
 */
	function __construct($servername,$username,
			$password,$DBname,$DefaultPath='./DBbackup/') {
		$this->db = mysql_connect($servername,$username,$password) 
			or die('Could not connect: '.mysql_error());
		
		$this->DBname = $DBname;
		$this->servername=$servername;
		$this->username=$username;

		mysql_select_db($DBname, $this->db);
		if(substr($DefaultPath, -1) != '/') {
			$DefaultPath .= '/';
		}
		$this->DefaultPath = $DefaultPath;

		mysql_query("SET NAMES 'utf8'",$this->db);
	}

/**
 * To check the backup file path and create it if not exist.
 *
 * @author Array-Huang
 * @copyright 2012/07/17
 * @access private
 * @param $path string
 * @return	bool
 */	
	private function CheckDir($path) {
		if(file_exists($path)){
			return true;
		}

		return mkdir($path,0700,true);
	}

/**
 * To check the backup file path and add the "/" if it does not exist.
 *
 * @author Array-Huang
 * @copyright 2012/07/17
 * @access private
 * @param $path string
 * @return	string/bool If the path is valid then return the path with "/" else return false.
 */	
	private function CheckPath($path) {
		if(! file_exists($path)) {
			return false;
		}else if(! is_dir($path)) {
			return false;
		}else {
			if(substr($path, -1) != '/') {
				$path .= '/';
			}
		}

		return $path;
	}


/**
 * Use 'SHOW TABLE STATUS' to get all the tables' names in the target DB.
 *
 * @author Array-Huang
 * @copyright 2012/07/15
 * @access public
 * @return	array/bool If worked then return a array(every child is a table's name) else return false.
 */
	public function GetTablesName() {
		$names = array();
		$result = mysql_query('SHOW TABLE STATUS',$this->db);
		if(empty($result)){
			return false;
		}
		while($row = mysql_fetch_array($result)) {
			$names[] = $row['Name'];
		}
		return($names);
	}

/**
 * Use 'SHOW CREATE TABLE' to get the DDL commands for the specified table's data.
 * @author Array-Huang
 * @copyright 2012/07/15
 * @access private
 * @param	$table_name string
 * @return	string/bool
 */
	private function GetTableFields($table_name) 
    {
        $result = mysql_query('SHOW CREATE TABLE '.$table_name,$this->db); 
        $row = mysql_fetch_array($result);
        return ' '.$row[1].";\n";
    }

/**
 * Export the specified tables' data
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access private
 * @param	$table_name string
 * @return	string the exported data(SQL)
 */
	private function GetTableData($table_name) {
		$SQLString = '';
		
		$result = mysql_query('select * from '.$table_name,$this->db);
		
		if(empty($result)) {
			return '';
		}
		
		while($row = mysql_fetch_row($result)){
			$str = '';
			foreach ($row as $key => $value) {
				if(empty($str)){
					$str .= ' INSERT INTO'.'`'.$table_name.'` VALUES ( \''.$value.'\'';
				}else {
					$str .= ',\''.$value.'\'';
				}
			}
			$str .= " );\n";
			$SQLString .= $str;
			
		}
		return $SQLString;
	}

/**
 * To create the information about the backup(it contains the DB server address、DB name、account、backup time) which located at the top of the exported file.
 * 
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access private
 * @return	string 
 */
	private function CreateFileHead() {
		$head = '';
		$head .= "/*\n";
		$head .= "Array-Huang MySQL Data Transfer\n";
		$head .= "\n";
		$head .= 'Source Host         : '."$this->servername\n";
		$head .= 'Source Database     : '."$this->DBname\n";
		$head .= 'User Name           : '."$this->username\n";
		$head .= "\n";
		$head .= 'Date : '.date("Y-m-d H:i:s")."\n";
		$head .= "*/\n";
		$head .= "\n";
		return $head;
	}

/**
 * To create the information about the specified tables.
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access private
 * @param	$table_name string
 * @return	string
 */
	private function CreateTableHead($table_name) {
		$head = '';
		$head .= 'SET FOREIGN_KEY_CHECKS=0;'."\n";
		$head .= "\n";
		$head .= "\n";
		$head .= '-- ----------------------------'."\n";
		$head .= '-- Table  : `'.$table_name.'`'."\n";
		$head .= '-- ----------------------------'."\n";

		return $head;
	}

/**
 * To write all the exported data to the backup file.
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @param	$table_name string
 * @param	$path string
 * @return	int/bool If susscessful , return the number of byte else return false.
 */
	public function WriteTableToFile($table_name,$path = '') {
		if(empty($path)){
			$path = $this->DefaultPath;
		}elseif(substr($path, -1) != '/') {
			$path .= '/';
		}
		if(! $this->CheckDir($path)) {
			return false;
		}

		$SQLString = $this->CreateFileHead().$this->CreateTableHead($table_name);
		$SQLString .= 'DROP TABLE IF EXISTS `'.$table_name.'`;'."\n";
		$SQLString .= $this->GetTableFields($table_name);		
		$SQLString .= $this->GetTableData($table_name);		

		$filename = $path.$table_name.date("YmdHis").'.sql';

		return file_put_contents($filename,$SQLString);
	}

/**
 * To call function WriteTableToFile.
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @param	$path string
 * @return	bool/int
 */
	public function WriteAllToFile($path = '') {
		$table_names = $this->GetTablesName();
		if(false === $table_names) {
			return false;
		}else {
			foreach ($table_names as $key => $table_name) {
				$result = $this->WriteTableToFile($table_name,$path);
				if(false === $result) {
					return false;
				}
			}
		}
		return true;
	}

/**
 * To provide download of the backup file of a table.
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @param	$table_name string	
 * @return	file the backup file name's format is 'table name + year+month+day+hour+minute+second'
 */	
	public function DownloadTable($table_name) {
		$SQLString = $this->CreateFileHead().$this->CreateTableHead($table_name);
		$SQLString .= 'DROP TABLE IF EXISTS `'.$table_name.'`;'."\n";
		$SQLString .= $this->GetTableFields($table_name);
		$SQLString .= $this->GetTableData($table_name);

		$filename = $table_name.date("YmdHis").'.sql';

		header("content-type: application/octet-stream");
		header("accept-ranges: bytes");
		header("accept-length: ".strlen($SQLString));
		header("content-disposition: attachment; filename=".$filename );

		echo $SQLString;
	}

/**
 * To provide download of the backup file of tables.
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @return file the backup file name's format is 'table name + year+month+day+hour+minute+second'
 */
	public function DownloadAllTables() {
		$SQLString = $this->CreateFileHead();

		$table_names = $this->GetTablesName();
		if(false === $table_names) {
			return false;
		}else {
			foreach ($table_names as $key => $table_name) {
				$SQLString .= $this->CreateTableHead($table_name);
				$SQLString .= 'DROP TABLE IF EXISTS `'.$table_name.'`;'."\n";
				$SQLString .= $this->GetTableFields($table_name);
				$SQLString .= $this->GetTableData($table_name);
			}
		}

		$filename = $table_name.date("YmdHis").'.sql';

		header("content-type: application/octet-stream");
		header("accept-ranges: bytes");
		header("accept-length: ".strlen($SQLString));
		header("content-disposition: attachment; filename=".$filename);

		echo $SQLString;
	}

/**
 * inquiry of all files' names in the path
 *
 * @author Array-Huang
 * @copyright 2012/07/17
 * @access public
 * @param	$path string	like './abc/'
 * @return	array/bool
 */
	public function GetFileNames($path='./') {
		$path = $this->CheckPath($path);
		if(false === $path) {
			return false;
		}

		$filenames = array();

		$dir = opendir($path);

		while (($file = readdir($dir)) !== false)
  		{
  			if(! is_dir($path.$file)) {
  				$filenames[] = $file;
  			}  			
  		}

  		closedir($dir);

  		return($filenames);
	}

/**
 * To import the backup file(SQL)
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 * @param	$path string
 * @return	bool
 */	
	public function ReadTableFromFile($path) {
		if(! file_exists($path)) {
			return false;
		}

		$contents = file_get_contents($path);
		$sql = explode(";",$contents);

		foreach ($sql as  $value) {
			mysql_query($value.';',$this->db);
		}

		return true;
	}

/**
 * To import all the backup files(SQL) in the path
 * @author Array-Huang
 * @copyright 2012/07/17
 * @access public
 * @param	$path string
 * @return	bool
 */
	public function ReadAllTablesFromFiles($path) {
		$path = $this->CheckPath($path);
		if(false === $path) {
			return false;
		}

		$filenames = $this->GetFileNames($path);
		if(false === $filenames) {
			return false;
		}

		foreach ($filenames as $key => $value) {
			$result=$this->ReadTableFromFile($path.$value);
			if(false === $result) {
				return false;
			}
		}

		return true;
	} 

/**
 * DB physical backup
 * shutdown mysql-->copy data/log/control file-->startup mysql
 * Required files:
 * 	MyISAM Mode：
 *		mysql Variable datadir
 * 	InnoDB Mode：
 *		1.mysql Variable datadir
 *		2.mysql Variable innodb_data_home_dir&&innodb_data_file_path
 *		3.mysql Variable innodb_log_group_home_dir
 *
 * @author Array-Huang
 * @copyright 2012/07/17
 * @access public
 * @todo
 */
	public function PhysicalBak() {
		/*todo*/
	}

/**
 * To close the connection
 *
 * @author Array-Huang
 * @copyright 2012/07/16
 * @access public
 */
	public function __destruct() {
      mysql_close($this->db);
    }
}
?>	