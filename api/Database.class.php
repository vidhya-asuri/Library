<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '1024M');

include_once("File.class.php");

class DBVals
{
	const TYPE_NULL  = -1;
	const TYPE_MSSQL = 0;
	const TYPE_MYSQL = 1;
	const TYPE_DSN   = 2;

	const MYSQL_DEFAULT_PORT = 3306;
	const MSSQL_DEFAULT_PORT = 1433;

	/* Database network locations */
	const HOST_IC = "10.10.102.7";
	const HOST_INTRANET = "10.10.102.41";
	const HOST_VAST = "10.10.10.34";
	const HOST_VAST_DEV = "10.10.10.35";
	const DSN_MCO = "iSeriesDB4allUser";
}

/**
	* Factory class for the Database class for easy connection creation
*/
class DBFactory
{
	public static function getPODB() // Instance of the Purchase Order database hosted on the Intranet db server.
	{
		return new Database(DBVals::HOST_INTRANET, DBVals::MYSQL_DEFAULT_PORT,
				'intranetrw', 'aSw2017', 'INTRANET', DBVals::TYPE_MYSQL);
	}
	
	
    public static function getIntranetDB()
    {
        return new Database(DBVals::HOST_INTRANET, DBVals::MYSQL_DEFAULT_PORT,
            'intranetrw', 'aSw2017', 'INTRANET', DBVals::TYPE_MYSQL);
    }

    public static function getICDB()
    {
        return new Database(DBVals::HOST_IC, DBVals::MYSQL_DEFAULT_PORT,
            'alldbuser', 'nosoupforyou', 'IC', DBVals::TYPE_MYSQL);
    }

    // TODO: remove once we're certain that nothing depends on it
    public static function getVastDB()
    {
        return new Database(DBVals::HOST_VAST, DBVals::MSSQL_DEFAULT_PORT,
            'ssdbadmin', 'N3wP0sSyS18', 'VastOffice', DBVals::TYPE_MSSQL);
    }

    public static function getMCODB()
    {
        return new Database(DBVals::DSN_MCO, $port=null,
            "U537READO", "hh23ff95", $dbname=null, DBVals::TYPE_DSN);
    }
}

class Database
{

	private $_hostname;
	private $_port;
	private $_username;
	private $_password;
	private $_dbname;
	
	private $_connection;

	/**
		* DBVals constant defining the type of database being used
		* Options can be found in DBVals abstract class
	*/
	private $_dbtype;

    private $_log_file;
    private $_log_path;

	/**
		* Latest error message logged by the database class.
		* Set during exception handling. This should be pulled with
		* errorCheck() and ultimately returned to the frontend.
	*/
	private $_errMsg;

    /**
        * If error message severity isn't above this level, don't log it.
    */
    private $_system_severity;

    /* Log class severity levels. See notes in writeToLog. */
    const EMERGENCY = "EMERGENCY";
    const ALERT     = "ALERT";
    const CRITICAL  = "CRITICAL";
    const ERROR     = "ERROR";
    const WARNING   = "WARNING";
    const NOTICE    = "NOTICE";
    const INFO      = "INFO";
    const DEBUG     = "DEBUG";

    /**
     * Used to compare severity levels
     */
    private $_severity_map = array(
        "EMERGENCY" => 0,
        "ALERT" => 1,
        "CRITICAL" => 2,
        "ERROR" => 3,
        "WARNING" => 4,
        "NOTICE" => 5,
        "INFO" => 6,
        "DEBUG" => 7
    );


	function __construct($hostname=null,$port=null,$username=null,$password=null,$dbname=null,$dbtype=null)
	{
		$this->_hostname = $hostname;
		$this->_port = $port;
		$this->_username = $username;
		$this->_password = $password;
		$this->_dbname = $dbname;
		$this->_dbtype = $dbtype;
		$this->_errMsg = "";
		$this->_connection = null;
	}

	/**
		* Establish a PDO connection to the database.
		*
		* Uses PDO DSN string to form a connection. These may be database
		* type specific. To set a database type, be sure _dbtype is set with
		* one of the TYPE_ variables from DBVals.
	*/
	public function connect()
	{
		if (!isset($this->_dbtype))
		{
			$this->_dbtype = DBVals::TYPE_NULL;
		}

		$db_str = "";

		try
		{
			switch ($this->_dbtype)
			{
				case DBVals::TYPE_MYSQL:

					$db_str = "mysql:host=".$this->_hostname.";port=".$this->_port.";dbname=".$this->_dbname;
					$this->_connection = new PDO($db_str,
					        $this->_username, $this->_password
					);
					break;


				case DBVals::TYPE_MSSQL:
					
					$db_str = "sqlsrv:server=".$this->_hostname.", ".$this->_port.";database=".
						$this->_dbname;
			
					$this->_connection = new PDO($db_str, $this->_username, $this->_password);
					break;

				case DBVals::TYPE_DSN:

					$db_str = "odbc:DSN=".$this->_hostname.";Uid=".$this->_username.";Pwd=".$this->_password.";";
					$this->_connection = new PDO($db_str);
					break;

				default:
					$this->_connection = null;
			}
		}
		catch (PDOException $e)
		{
			$this->setSessionSeverity(Database::NOTICE);
			$this->setLogFile(".", "intranet.log");

			$msg = $e->getMessage().". ".$e->getTraceAsString();
			$this->_errMsg = $e->getMessage();

			$this->writeToLog($msg, Database::ALERT);
			$this->disconnect();
			return false;
		}

		$this->_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if ($this->_hostname === DBVals::HOST_INTRANET)
		{
			// Set logging severity, log file locations
			$sql = "SELECT VAR_NAME, VALUE FROM CONFIG WHERE VAR_NAME IN ('LOGGING_SEVERITY','LOGGING_PATH','LOGGING_FILE')";
			$result = $this->query($sql);
			$arr = $result->fetchAll(PDO::FETCH_KEY_PAIR);

			$this->setSessionSeverity($arr['LOGGING_SEVERITY']);
			$this->setLogFile($arr['LOGGING_PATH'], $arr['LOGGING_FILE']);
		}
		else
		{
			$this->setSessionSeverity(Database::INFO);
			$this->setLogFile(".", "intranet.log");
		}

        return true;
	}

	public function disconnect()
	{
		if (isset($this->_connection))
		{
			$this->_connection = null;
		}
	}

	/**
	 * Run a PDO database query.
	 *
	 * Running this with no arguments will just run the written query.
	 * Running with arguments will execute a prepared statement in PDO.
	 * // TODO: We need to use bindParam after we do prepare for security.
	 * // Don't do prepare() in a loop when you implement bindParams.
	 *
	 * @param $str string SQL statement to execute
	 * @param $args mixed arguments to replace into a prepared statement
	 * @return mixed PDO statement or FALSE on failure
	 */
	public function query($str, $args=null)
	{
		try
		{
			if (!isset($this->_connection))
			{
				return false;
			}

			$pdo = $this->_connection;
			
			// Typical query with no args
			// TODO: take this out?
			if (!isset($args))
			{
				$stmt = $pdo->query($str);
				return $stmt;
			}
		
			// Positional arguments or named arguments
			$stmt = $pdo->prepare($str);
		
			$stmt->execute($args);
		
			return $stmt;
		}
		catch (PDOException $e)
		{
            $msg = $e->getMessage().". ".$e->getTraceAsString();
            $this->_errMsg = $e->getMessage();
            $this->writeToLog($msg, Database::INFO);
			return false;
		}
	}

	/**
		*****************
		* Utility methods
		*****************
	*/

	// Avoid log class write. log class write calls the db on its own to
	// perform log writes and may create an infinite loop. additionally,
	// this helps in the case that we simply cannot connect to a database.
	public function writeToLog($message, $severity)
	{
        $message_severity = "Unknown";

        // Assign proper severity label
        switch(strtoupper($severity))
        {
            // intentional fall-through
            case Database::EMERGENCY:
            case Database::ALERT:
            case Database::CRITICAL:
            case Database::ERROR:
            case Database::WARNING:
            case Database::NOTICE:
            case Database::INFO:
            case Database::DEBUG:
                $message_severity = strtoupper($severity);
                break;
            default:
                $message_severity = $message_severity.": ".$severity;
                break;
        }

        if ($this->_system_severity < $this->_severity_map[$message_severity])
        {
            return;
        }

        // we're just going to steal the apache format
        $timestamp = date('D M j G:i:s Y');
		$line = "[".$timestamp."] (".$message_severity.") ".$message.PHP_EOL;

		$logfile = new File;
		$logfile->writeToFile($this->_log_path, $this->_log_file, $line);
	}

	/**
		* Vidhya's utility method to select key[value] set from the given table
		*
		* @param $table - table to query from
		* @param $key, name of the 'key' column
		* @param $value, name of the 'value' column
		* @return mixed reference array with $keyValuePairs[key] = value or false on failure
		*
	*/
	public function selectKeyValuePair($table, $key, $value)
	{
		$sql = "SELECT $key, $value FROM $table";

		$keyValuePairs = array();

		$result = $this->query($sql);

		if ($result == false)
		{
			return false;
		}

		while($row = $result->fetch(PDO::FETCH_NUM))
		{
		     $keyValuePairs[$row[0]] = $row[1];
		}

		return $keyValuePairs;
	}

	/**
		* Connect, query, fetch, and return a result. Reduces verbosity. DB
		* should be initialized before using this.
		*
		* @param $sql SQL query to execute.
		* @param $args Arguments for a PDO prepared statement
		* @param $fetchStyle PDOStatement fetch_style to use.
		* @return Array of data, possibly empty, from PDOStatement::fetchAll()
		* 	or false on failure.
	*/
	public function queryAndFetchAll($sql, $args=null, $fetchStyle=PDO::FETCH_ASSOC)
	{
		if (!isset($this->_connection))
		{
			if (!$this->connect())
			{
				return false;
			}
		}

		$result = $this->query($sql, $args);

		if(!$result)
		{
			return false;
		}

		return $result->fetchAll($fetchStyle);
	}

	/**
	 * Copy SQL from source db to a table in this db.
	 *
	 * @param $toTable Destination table name
	 * @param $fromDB Source Database object
	 * @param $sql Query to run
	 * @param $args (Optional) Parameters for $sql prepared statement
	 * @param $debug (Optional) If true, log debug messages
	 * @return True or false on failure
	 */
	public function copyIntoTable($toTable, $fromDB, $sql,
		$args=array(), $debug=false)
	{
		// need both connections to do a copy
		if (!isset($this->_connection))
		{
			if (!$this->connect())
			{
				// unable to connect to the database
				return false;
			}
		}

		if ($fromDB->getConnection() === null)
		{
			if (!$fromDB->connect())
			{
				// unable to connect to the other database
				return false;
			}
		}

		// one hour
		ini_set('max_execution_time', 3600);

		if ($debug)
		{
			$msg = "[Database copyTable] From: ".$fromDB->getHostname()." to ".$toTable;
			$this->writeToLog($msg, Database::DEBUG);
			$msg = "[Database copyTable] SQL: ".$sql;
			$this->writeToLog($msg, Database::DEBUG);
		}

		// prepare with a cursor
		$fromPDO = $fromDB->getConnection();
		$stmt = $fromPDO->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
		$stmt->execute($args);

		if (!$stmt)
		{
			// query failed. in the case of the sync, this most likely means that
			// we've lost our connection to maddenco.
			if ($debug)
			{
				$msg = "[Database copyTable] Query failed with: ".$fromDB->errorCheck();
				$this->writeToLog($msg, Database::DEBUG);
			}

			return false;
		}

		// TODO: we may need to up the memory limit to use this with maddenco tables
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (count($data) === 0)
		{
			// maddenco's odbc doesn't support row count, so just return true
			return true;
		}

		/* Do a series of insert queries. Each insert query will have
		   $maxInserts rows to insert. Each of these rows will have
		   a comma separated series of placeholder ?s for a prepared
		   statement. */

		// number of keys in each row of $data
		$valueSetCount = count(array_keys($data[0]));

		// keep track of how many value sets have been appended to $args
		$insertCounter = 0;

		// TODO: move this to php.ini
		// number of rows to insert with each query
		$maxInserts = 50;

		// column names for insert
		$columns = implode(", ", array_keys($data[0]));

		// create a set of comma-separated ? placeholders for one value set in the query for a prepared statement
		// INSERT INTO table (A, B, C, D, ...) VALUES (?,?,?,?, ...)
		$placeholders = implode(', ', array_fill(0, $valueSetCount, '?'));

		$insertSQL = "INSERT INTO ".$toTable." (".$columns.") VALUES (".$placeholders.")";

		// repeat the value set placeholder for each row to be inserted
		$insertSQL .= str_repeat(', ('.$placeholders.")", $maxInserts - 1);

		// individual column values, in order, for the prepared statement
		$args = array();

		$pdo = $this->getConnection();
		$stmt = $pdo->prepare($insertSQL);

		// iterate over $data and, for every $maxInserts rows,       do an insert
		foreach ($data as $row)
		{
			foreach ($row as $key => $value)
			{
				$args[] = $value;
			}

			$insertCounter++;

			if ($insertCounter === $maxInserts)
			{
				// $maxInsert rows are in the query, execute it
				// TODO: error check
				$stmt->execute($args);
				$args = array();
				$insertCounter = 0;
			}
		}

		if ($insertCounter !== 0)
		{
			// insert the remaining rows
			$placeholders = implode(', ', array_fill(0, $valueSetCount, '?'));

			$insertSQL = "INSERT INTO ".$toTable." (".$columns.") VALUES (".$placeholders.")";
			$insertSQL .= str_repeat(', ('.$placeholders.")", $insertCounter - 1);

			$stmt = $pdo->prepare($insertSQL);
			$stmt->execute($args);
		}
		return true;
	}

	/**
	 * Old utility function from the IC. Pulls a key => value array from the
	 * connected database. Allows an override to pull an array containing the
	 * desired data as well. Many IC reporting functions use this method and
	 * expect this format, so it's simpler to just include it.
	 *
	 * @param $table Name of table to query
	 * @param $key Column to use as an index
	 * @param $value Column to use as an indexed value
	 * @param $sql_override Optional SQL query string
	 * @param $args Arguments for a PDO prepared statement
	 * @return key => value array on success, false on failure
	 */
	public function getReferenceArray($table, $key, $value, $sql_override="", $args=null)
	{
		$sql = "SELECT $key, $value FROM $table";

		if (!empty($sql_override))
		{
			$sql = $sql_override;
		}

		$result = $this->query($sql, $args);

		if (!$result)
		{
			return false;
		}

		$data = $result->fetchAll(PDO::FETCH_NUM);

		$return_data = array();

		foreach ($data as $row)
		{
			$return_data[$row[0]] = ucwords($row[1]);
		}

		return $return_data;
	}

	/**
	 * Get a key->value array of config key from the config table.
	 *
	 * @param $var_name String var_name to query
	 * @return key => value array, or false on failure
	 */
	public function getConfigKey($var_name)
	{
		if (!isset($this->_connection))
		{
			if (!$this->connect())
			{
				// unable to connect to the database
				return false;
			}
		}

		$tables = array(
			DBVals::HOST_INTRANET => "CONFIG",
			DBVals::HOST_IC => "IC_CONFIG"
		);

		if (!in_array($this->getHostname(), array_keys($tables)))
		{
			// only icdb and indb have relevant config tables
			return false;
		}

		$table = $tables[$this->getHostname()];

		$sql = "SELECT VAR_NAME, VALUE FROM $table WHERE VAR_NAME = ? ";
		$args = array($var_name);

		$result = $this->query($sql, $args);
		if ($result)
		{
			return $result->fetch();
		}
		else
		{
			return false;
		}
	}

	/** 
	 * Set a config value in a config table.
	 *
	 * @param $var_name String var_name to update
	 * @param $value Value to set
	 * $return true if update affected rows, false if not or failure
	 */
	public function setConfigKey($var_name, $value)
	{
		if (!isset($this->_connection))
		{
			if (!$this->connect())
			{
				// unable to connect to the database
				return false;
			}
		}

		$tables = array(
			DBVals::HOST_INTRANET => "CONFIG",
			DBVals::HOST_IC => "IC_CONFIG"
		);

		if (!in_array($this->getHostname(), array_keys($tables)))
		{
			// only icdb and indb have relevant config tables
			return false;
		}

		$table = $tables[$this->getHostname()];

		$sql = "UPDATE $table SET VALUE = ? WHERE VAR_NAME = ?";
		$args = array($value, $var_name);

		$stmt = $this->query($sql, $args);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Taken from stackoverflow. This has been useful for me a few times so I
	 * figured it would be helpful to keep it in here. Use it like query(), but
	 * it will return a string.
	 */
	public static function interpolateQuery($query, $params) {
	    $keys = array();

	    # build a regular expression for each parameter
	    foreach ($params as $key => $value) {
	        if (is_string($key)) {
	            $keys[] = '/:'.$key.'/';
	        } else {
	            $keys[] = '/[?]/';
	        }
	    }

	    $query = preg_replace($keys, $params, $query, 1, $count);

	    #trigger_error('replaced '.$count.' keys');

	    return $query;
	}

	/**
		* Check for and return any error messages that were set
		* @return Error message, blank message if there is none
	*/
	public function errorCheck()
	{
		return $this->_errMsg;
	}

	/**
		* Clear the error message that has been set
	*/
	public function errorClear()
	{
		$this->_errMsg = "";
	}


	/**
		Getters/Setters

		Returning $this allows us to chain set methods like so:

		$someDB = (new Database())
			->setDBType(Database::DBVals::MYSQL)
			->setHostname('db.yourhost.xyz')
			->setPort(Database::MYSQL_DEFAULT_PORT)
			->setUsername('username')
			->setPassword(password)
			->setDBName('dbname');

		This should only be done if really needed. If we run into a case where
		we do this a lot, then it'll be better to add that case to DBFactory.
	*/

    public function setSessionSeverity($severity)
    {
        $this->_system_severity = $this->_severity_map[$severity];
    }

    public function setLogFile($path, $filename)
    {
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
		{
		    $path = "";
		}

		$this->_log_file = $filename;
		$this->_log_path = $path;
    }

	public function setHostname($hostname)
	{
		$this->_hostname = $hostname;
		return $this;
	}
	
	public function setPort($port)
	{
		$this->_port = $port;
		return $this;
	}

	public function setUsername($username)
	{
		$this->_username = $username;
		return $this;
	}

	public function setPassword($password)
	{
		$this->_password = $password;
		return $this;
	}

	public function setDBName($dbname)
	{
		$this->_dbname = $dbname;
		return $this;
	}

	public function setDBType($dbtype)
	{
		$this->_dbtype = $dbtype;
		return $this;
	}

	public function getConnection()
	{
		return $this->_connection;
	}

	public function getHostname()
	{
		return $this->_hostname;
	}
	
	public function getPort()
	{
		return $this->_port;
	}

	public function getUsername()
	{
		return $this->_username;
	}

	public function getPassword()
	{
		return $this->_password;
	}

	public function getDBName()
	{
		return $this->_dbname;
	}

	public function getDBType()
	{
		return $this->_dbtype;
	}
}

?>
