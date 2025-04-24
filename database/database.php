<?php
// DB class
class DB{
	// variables for connection
	private static $dsn = 'mysql:host=localhost;dbname=hot_iss_tables;charset=utf8mb4';
	private static $user = 'root';
	private static $pass = '';
	private static $conn = null;

	public function __construct() {
		exit("Constructor not needed");
	}

	public static function connect() {
		//if no current connection
		if (null == self::$conn) {
			//attempt to make connection
			try {
				self::$conn = new PDO(self::$dsn, self::$user, self::$pass);
			}
			catch(PDOException $e) { die($e->getMessage()); }
		}
		//return the connection
		return self::$conn;
	}
}
?>
