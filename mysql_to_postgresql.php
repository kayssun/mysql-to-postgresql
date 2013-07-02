<?
/*
Created by Gabriel Bordeaux / http://www.gab.lc
Version 1.0 - December 18th 2012

Important infos :
=> I created this program for my personnal use, there is absolutely no warranty that it'll work in your configuration/with your databases.
=> Please backup your MySQL Table before to try this script
=> If the tables exists in the PostgreSQL database, they'll be truncated => be carrefull if you want to keep the original datas
=> You need to launch the PHP program from a CLI (mainly because of PHP timeouts).

Howto :
=> Fill the MySQL and PostgreSQL connection infos bellow
=> Then just run "php my2pg.php" from your termunal
*/

// Memory limit
ini_set("memory_limit","1024M");

// MySQL connection
$MyHost = "localhost";
$MyUser = "gab_login";
$MyPass = "gab_password";

// PostgreSQL connection
$PgHost = "localhost";
$PgUser = "gab_login";
$PgPass = "gab_password";
$PgEncoding = "UTF8"; // LATIN1, UTF8...

// Retrieve limit
$RetrieveLimit = 100000; // How many lines should we retrieve from a table at a time => Limit the memory used by the script on your server

// Vars
$db = $argv[1];

// MySQL connection
echo "* MySQL connection...\n";
$MyConn = mysql_connect($MyHost, $MyUser, $MyPass);
if(!$MyConn) {
	echo "* Error: Mysql connection impossible";
	echo "* EXITING! (sorry about that)\n";
	exit();
}

// PostgreSQL connection
echo "* PostgreSQL connection...\n";
$PgConn = pg_connect("host=".$PgHost." port=5432 user=".$PgUser." password=".$PgPass);
if(!$PgConn) {
	echo "* Error: PostgreSQL connection impossible\n";
	echo "* EXITING! (sorry about that)\n";
	exit();
}

if(!$db) { // The user did not ask to import any db
	// List of MySQL databases
	echo "* List of MySQL databases...\n";
	$res = mysql_query("SHOW DATABASES");
	while ($row = mysql_fetch_assoc($res)) {
		if($row['Database'] != "information_schema" && $row['Database'] != "mysql") {
			echo "** ".$row['Database']." => to import this db, call: php ".$_SERVER['SCRIPT_NAME']." ".$row['Database']."\n";
		}
	}
} else { // The user asked to import a specific db
	// Db selection
	echo "* Selection of MySQL database \"".$db."\"...\n";
	if(!mysql_select_db($db)) {
		echo "* Error: Impossible to select MySQL database \"".$db."\"\n";
		echo "* EXITING! (sorry about that)\n";
		exit();
	}
	
	// Does the db exists in pg ?
	$res = pg_query($PgConn, "SELECT 1 from pg_database WHERE datname='".$db."'");
	$numrows = pg_numrows($res);
	if($numrows == 0) {
		// Pg db creation
		echo "* Creation of the PostgreSQL database \"".$db."\"...\n";
		pg_query($PgConn, "CREATE DATABASE ".$db." ENCODING '".$PgEncoding."';");
	}
	
	echo "* Connection of the PostgreSQL database \"".$db."\"...\n";
	pg_close();
	$PgConn = pg_connect("host=".$PgHost." port=5432  dbname=".$db." user=".$PgUser." password=".$PgPass);
	if(!$PgConn) {
		echo "* Error: PostgreSQL connection to the database \"".$db."\" impossible.\n";
		echo "* EXITING! (sorry about that)\n";
		exit();
	}
	
	echo "* List of MySQL tables in \"".$db."\"...\n";
	$res_tables = mysql_query("SHOW TABLES FROM ".$db, $MyConn);
	if (!$res_tables) {
		echo "* Error: Impossible to list MySQL tables for the database \"".$db."\"\n";
		echo "* EXITING! (sorry about that)\n";
		exit();
	}
	while ($row_tables = mysql_fetch_row($res_tables)) { // List of tables
		// Vars
		$skip = false; // Will be "true" if we skip the table later
		$table = $row_tables[0]; // Table Name
		
		// Here is a little example on how to proceed to skip a specific table
		/*
		if($table == "log_actions") {
			$skip = true;
		}
		*/
		
		echo "...\n";
		echo "** Table \"".$table."\"\n";
		
		if($skip == false) { // Exept if we skip the table!
			// Check the number of entries in MySQL
			$res_num = mysql_query("SELECT count(*) as nb FROM ".$table, $MyConn);
			$MyNumEntries = mysql_fetch_assoc($res_num);
			echo "*** There is ".number_format($MyNumEntries['nb'], 0)." entries in this MySQL table.\n";
			
			// Check if the table exists in PostgreSQL
			$res = pg_query($PgConn, "SELECT 1 FROM information_schema.tables WHERE table_name = '".$table."'");
			$numrows = pg_numrows($res);
			if($numrows == 0) {
				echo "*** The table does not exists in the PostgreSQL db.\n";
				
				// We get the schema from MySQL
				$res_schema = mysql_query("SHOW CREATE TABLE ".$table, $MyConn);
				$tab_schema = mysql_fetch_assoc($res_schema);
				$MySchema = $tab_schema['Create Table'];
				
				// Conversion
				$PgSchema = $MySchema;
				$PgSchema = str_replace("`", "", $PgSchema); // Removes the "`" that pg does not like
				$PgSchema = str_replace("int(4) unsigned zerofill", "bigint", $PgSchema); // Specific replacement for ip2location
				$PgSchema = str_replace("int(16) unsigned", "bigint", $PgSchema); // Specific replacement for ip2location
				$PgSchema = str_replace("datetime", "timestamp", $PgSchema); // Convert the type "datetime" to "timestamp"
				$PgSchema = preg_replace("/bigint\([0-9]+\)/i", "bigint", $PgSchema); // Adapt the type "bigint"
				$PgSchema = preg_replace("/int\([0-9]+\)/i", "integer", $PgSchema); // Adapt the type "int"
				$PgSchema = preg_replace("/smallinteger\([0-9]+\)/i", "smallint", $PgSchema); // Adapt the type "smallint"
				$PgSchema = str_replace("smallinteger", "smallint", $PgSchema); // Adapt the type "smallint"
				$PgSchema = str_replace("tinyinteger", "smallint", $PgSchema); // Adapt the type "tinyint"
				$PgSchema = str_replace("tinyinteger", "smallint", $PgSchema); // Adapt the type "tinyint"
				$PgSchema = str_replace("mediuminteger", "integer", $PgSchema); // Adapt the type "tinyint"
				$PgSchema = str_replace("double(", "decimal(", $PgSchema); // Adapt the type "double"
				$PgSchema = preg_replace("/(tinytext|mediumtext|longtext)/i", "text", $PgSchema); // Adapt the type "text"
				$PgSchema = preg_replace("/(blob|tinyblob|mediumblob|longblob)/i", "bytea", $PgSchema); // Adapt the type "bytea"
				$PgSchema = preg_replace("/ENGINE=[0-9a-z]+/i", "", $PgSchema); // Remove the engine type
				$PgSchema = preg_replace("/DEFAULT CHARSET=[0-9a-z]+/i", "", $PgSchema); // Remove the default charset
				$PgSchema = preg_replace("/AUTO_INCREMENT=[0-9a-z]+/i", "", $PgSchema); // Remove the id first value
				$PgSchema = preg_replace("/PACK_KEYS=[0-9a-z]+/i", "", $PgSchema); // Remove the PACK_KEYS value
				$PgSchema = preg_replace("/ON UPDATE[0-9a-zA-Z ()_]+/i", "", $PgSchema); // Remove the "ON UPDATE" info, you'll need a trigger for that in PG
				$PgSchema = str_replace("NOT NULL AUTO_INCREMENT", "SERIAL NOT NULL", $PgSchema); // Convert the auto increment with not null
				$PgSchema = str_replace("AUTO_INCREMENT", "SERIAL", $PgSchema); // Convert the auto increment without not null
				$PgSchema = str_replace("unsigned", "", $PgSchema); // The type "UNSIGNED" is not available in PG
				$PgSchema = str_replace("zerofill", "", $PgSchema); // The type "ZEROFILL" is not available in PG
				$PgSchema = preg_replace("/(int|integer|bigint|smallint|tinyint|mediumint)[ ]+SERIAL/i", "SERIAL", $PgSchema); // Remove the indexes except the primary key
				$PgSchema = str_replace("UNIQUE KEY", "KEY", $PgSchema); // Transformation of "UNIQUE KEY" to "KEY"
				$PgSchema = preg_replace("/[^PRIMARY] Key [0-9a-z,() _]+/i", "", $PgSchema); // Remove the indexes except the primary key
				$PgSchema = preg_replace("/,[ \n]+\)/", "\n)", $PgSchema); // Correct the syntax in case the query end by ", );"
				$PgSchema = str_replace("DEFAULT '0000-00-00 00:00:00'", "DEFAULT NOW()", $PgSchema); // PG does not permit null dates/times
				$PgSchema = str_replace("DEFAULT '0000-00-00'", "DEFAULT current_date", $PgSchema); // PG does not permit null dates
				$PgSchema = str_replace("CURRENT_TIMESTAMP", "NOW()", $PgSchema); // Timestamp conversion
				$PgSchema = preg_replace("/COLLATE.utf8_bin/i", "", $PgSchema); // this COLLATION does not compute
				
				/*
				// If you want to debug a Schema without trying to create it in pg
				echo $PgSchema."\n\n";
				exit();
				*/
				
				// Creation of the table in pg
				echo "*** Creation of the table in PostgreSQL...\n";
				$res_creation = pg_query($PgConn, $PgSchema);
				$error = pg_last_error($PgConn);
				if($error) {
					echo "*** ...The table could not be created.\n";
					echo "*** ...Please try to create the table manually then re-run the script.\n";
					echo "*** ...Original schema (MySQL):\n";
					sleep(2);
					echo $MySchema."\n\n";
					echo "*** ...Converted schema (PostgreSQL):\n";
					sleep(2);
					echo $PgSchema."\n\n";
					echo "*** ...Error returned from PostgreSQL:\n";
					sleep(2);
					echo $error."\n\n";
					echo "* EXITING! (sorry about that)\n";
					exit();
				}
			} else {
				echo "*** The table exists in the PostgreSQL db...\n";
				
				// Number of entries in the Pg table
				$res_num = pg_query($PgConn, "SELECT count(*) as nb FROM ".$table);
				$PgNumEntries = pg_fetch_array($res_num, null, PGSQL_ASSOC);
				echo "*** There is ".number_format($PgNumEntries['nb'], 0)." entries in the PostgreSQL table.\n";
				
				// Comparaison of number of entries
				if($MyNumEntries['nb'] == $PgNumEntries['nb']) {
					echo "*** ...the tables seems identical!\n";
					echo "*** ...we SKIP this table!\n";
					$skip = true;
				} else {
					echo "*** Truncate of the PostgreSQL table...\n";
					pg_query($PgConn, "TRUNCATE TABLE ".$table);
					$error = pg_last_error($PgConn);
					if($error) {
						echo "*** ...Error returned from PostgreSQL:\n";
						echo $error."\n\n";
						echo "*** Please try manually then re-launch the script.\n";
						echo "* EXITING! (sorry about that)\n";
						exit();
					}
				}
			}
		} else { // Skip request
			echo "*** ...we SKIP this table!\n";
		}
		
		// Let's get the datas
		if($skip == false) { // Exept if we skip the table!
			// For how many entries should we verbose ?
			if($MyNumEntries['nb'] >     100000000) { $verbose = 10000000; }
			elseif($MyNumEntries['nb'] > 10000000)  { $verbose = 1000000; }
			elseif($MyNumEntries['nb'] > 1000000)   { $verbose = 100000; }
			elseif($MyNumEntries['nb'] > 100000)    { $verbose = 10000; }
			elseif($MyNumEntries['nb'] > 10000)     { $verbose = 1000; }
			elseif($MyNumEntries['nb'] > 1000)      { $verbose = 100; }
			else { $verbose = 0; } // No verbose
		
			// Number of lines
			echo "*** There is ".number_format($MyNumEntries['nb'], 0)." entries to import...\n";
			
			for ($i_retrieve = 0; $i_retrieve <= $MyNumEntries['nb']; $i_retrieve = $i_retrieve + $RetrieveLimit) {
				if($i_retrieve == 0) {
					echo "*** Retrieve the content from the MySQL table...\n";
				} else {
					echo "*** Retrieve more content from the MySQL table to limit memory usage...\n";
				}
				$MyDatas = mysql_query("SELECT * FROM ".$table." LIMIT ".$i_retrieve.", ".$RetrieveLimit, $MyConn);

				if($i_retrieve == 0) {
					echo "*** Starting import entries from \"".$table."\"...\n";
				}
				
				// Retrieve each entry
				$numr = mysql_num_rows($MyDatas);
				$numf = mysql_num_fields($MyDatas);
				for($i = 0; $i < $numr; $i++)
				{
					$fields_txt = "";
					$values_txt = "";
				
					for($j = 0; $j < $numf; $j++)
					{
				    	$infofields = mysql_fetch_field($MyDatas, $j);
				    	$field = $infofields->name;
				    	$value = mysql_result($MyDatas, $i, $field);
				    	
				    	if($infofields->blob) {
				    		$value = pg_escape_bytea($value);
				    		$value = "'".$value."'";
				    	} else {
				    		// Value
				    		$value = str_replace("`", "'", $value); // Replaces the "`" that pg does not like
				    		$value = str_replace("´", "'", $value); // Replaces the "´" that pg does not like
				    		$value = stripslashes($value);
				    		// $value = utf8_encode($value); // In case you want to change datas encoding, do it here
				    		$value = pg_escape_string($value);
				    		$value = "'".$value."'";
				    	
				    		// Corrections
				    		if($value == "'0000-00-00 00:00:00'") { $value = "1970-01-01 00:00:00"; } // PG does not permit null dates/times
				    		elseif($value == "'0000-00-00'") { $value = "1970-01-01"; } // PG does not permit null dates
				    	}
	
						// If I escape all fields, I run into more problemes, so just 'freeze' for now
						if($field=="freeze") $fields_txt .= '"' . $field . '", ';
				    	else $fields_txt .= $field . ', ';
				    	$values_txt .= $value.", ";
					}
					
					// Trim
					$fields_txt = trim($fields_txt, ", ");
					$values_txt = trim($values_txt, ", ");
					
					// Insert
					$insert_into_pg = "INSERT INTO ".$table." (".$fields_txt.") VALUES (".$values_txt.");\n\n";
					pg_query($PgConn, $insert_into_pg);
					$error = pg_last_error($PgConn);
					if($error) {
						echo "*** ...Error returned from PostgreSQL:\n";
						echo $error."\n\n";
						echo "*** ...The query was:\n";
						echo $insert_into_pg."\n\n";
						echo "*** Please fix this entry in the MySQL table then re-launch the script.\n";
						echo "* EXITING! (sorry about that)\n";
						exit();
					}
					
					// Verbose
					if($verbose != 0 && $i + $i_retrieve != 0 && is_int($i / $verbose)) {
						echo "*** ...".number_format($i + $i_retrieve, 0)." lines inserted in \"".$table."\"\n";
					}
				}
			}
			
			// Number of entries in the Pg table
			$res_num = pg_query($PgConn, "SELECT count(*) as nb FROM ".$table);
			$PgNumEntries = pg_fetch_array($res_num, null, PGSQL_ASSOC);
			echo "*** There is now ".number_format($PgNumEntries['nb'], 0)." entries in the PostgreSQL table.\n";
			if($MyNumEntries['nb'] == $PgNumEntries['nb']) {
				// Done with this table
				echo "*** We are done with the table \"".$table."\"! How cool is that?\n";
			} else {
				// We miss some lines!
				echo "*** ...".number_format($MyNumEntries['nb'] - $PgNumEntries['nb'], 0)." were not inserted for an unknown reason.\n";
				echo "*** Please check this issue then re-launch the script.\n";
				echo "* EXITING! (sorry about that)\n";
				exit();
			}
		}
	}
}

// Closing the connexions
echo "...\n";
echo "* Closing PostgreSQL connection...\n";
mysql_close($MyConn);
echo "* Closing MySQL connection...\n";
pg_close($PgConn);
echo "* Done.\n\n";
?>
