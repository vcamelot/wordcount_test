<?php

namespace WordCount;

class WordCountTest {

	private $db_host = 'homestead.db';
	private $db_user = 'homestead';
	private $db_password = 'secret';
	private $db_name = 'wordcount_test';
	private $conn;

	private $start_time, $execution_time;

	private $input_file, $handle;

	private $delimiters_pattern = "/[\\W+]/i";
	private $last_char_pattern = "/[\\W+\$]/i";
	private $buffer_length = 8192;

	private $uniques, $watchlist_matches;

	public function __construct($input_file) {
		$this->input_file = $input_file;
		$this->execution_time = 0;
		$this->uniques = [];		
	}

	public function parse() {
		$this->start_time = microtime(true);

		if (!file_exists($this->input_file)) {
			throw new \Exception("Input file " .$this->input_file. " not found");
		}

		$this->handle = fopen($this->input_file, 'r');

		$broken_word = '';
		while (!feof($this->handle)) {
			$pieces = [];			
			$chunk = fread($this->handle, $this->buffer_length) or die($php_errormsg);
						
			// if the last read cycle ended with a part of the word, prepend it to current chunk
			$line = $broken_word . strtolower($chunk);
			
			$pieces = preg_split($this->delimiters_pattern, $line, -1, PREG_SPLIT_NO_EMPTY);

			// if the last chunk doesn't end with word separator, save it
 			// to prepend to the next section that gets read
 			if (count($pieces) > 0) {
				$last_piece = $pieces[count($pieces) - 1];
				if (!preg_match($this->last_char_pattern, mb_substr($chunk, -1, 1))) {
					array_pop($pieces);
					$broken_word = $last_piece;
				} else {
					$broken_word = '';
				}
			}

			// iterate over recently gathered pieces and see if it is not found in uniques array
			foreach($pieces as $word) {
				if (!isset($this->uniques[$word])) {
					$this->uniques[$word] = 1;
				}
			}

			// !!!
			// it appears that in_array() works 4-5 times slower than isset()
			// !!!
			/*foreach($pieces as $word) {
				if (!in_array($word, $this->uniques)) {
					$this->uniques[] = $word;
				}
			}*/
			
		}
		fclose($this->handle);

		unset($pieces);

		$this->openDBConnection();
		$this->updateUniquesTable();
		$this->selectWatchlistMatches();
		$this->closeDBConnection();
		
		$this->execution_time = microtime(true) - $this->start_time;
	}

	public function getUniqueCount() {
		return count($this->uniques);
	}

	public function getWatchlistMatches() {
		return $this->watchlist_matches;
	}

	public function getExecutionTime() {
		return $this->execution_time;
	}


	private function openDBConnection() {
		$this->conn = mysqli_connect($this->db_host, $this->db_user, $this->db_password, $this->db_name);
		if (!$this->conn) {
			throw new \Exception("Unable to connect to MySQL: " .mysqli_connect_errno());
		}
	}

	private function closeDBConnection() {
		mysqli_close($this->conn);
	}

	private function updateUniquesTable() {
		$query = "TRUNCATE TABLE uniques";
		mysqli_query($this->conn, $query);

		$query = "INSERT INTO uniques(word) VALUES(\"" .implode("\"), (\"", array_keys($this->uniques)) . "\")";		
		mysqli_query($this->conn, $query);
	}

	private function selectWatchlistMatches() {		
		$watchlist_words = [];

		$query = "SELECT LOWER(word) AS word_lcase FROM watchlist";
		$result = mysqli_query($this->conn, $query);
		while ($row = $result->fetch_array()) {
			$watchlist_words[] = $row["word_lcase"];
		}

		$this->watchlist_matches = array_intersect(array_keys($this->uniques), $watchlist_words);
		sort($this->watchlist_matches);

		unset($result);
	}
}

set_time_limit(300);

if (!isset($argv[1])) {
	echo "Usage format: wordcount.php INPUT_FILE";
	exit;
}

$input_file = $argv[1];

echo PHP_EOL. "=== Parsing started ===". PHP_EOL;

$wordcount = new WordCountTest($input_file);
$wordcount->parse();

echo PHP_EOL;
echo "Distinct unique words: " .$wordcount->getUniqueCount();
echo PHP_EOL;
echo "Watchlist words:";
echo PHP_EOL;
$watchlist_matches = $wordcount->getWatchlistMatches();
if (count($watchlist_matches) > 0) {
	foreach($watchlist_matches as $match) {
		echo $match;
		echo PHP_EOL;
	}
} else {
	echo "--- No matches or no watchlist words provided ---";
	echo PHP_EOL;
}
echo PHP_EOL. "=== Parsing finished ===" .PHP_EOL;
echo sprintf("Processed in %.3f seconds", $wordcount->getExecutionTime());
echo PHP_EOL;