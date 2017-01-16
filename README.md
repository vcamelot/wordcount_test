## Introduction

This PHP script reads text files, detects unique words and saves them into the MySQL table. If the database has table 'watchlist' with list of specific words, the script will display all words from watchlist found in the input file.

The input file may have or may not have line breaks, it is being parsed using binary read.

## Usage

Create MySQL database, import tables via dump.sql and set up MySQL connection parameters in wordcount.php.

Run:

*php wordcount.php INPUT_FILE*

## Configuration

MySQL configuration parameters are set in lines 7 to 10.

The size of the read buffer is set in line 19.

Max execution time is set in line 137 (for large files the standard timeout of 30 seconds can be not enough and the execution will be aborted).

## Test results

After playing with different read buffer sizes I came to the conclusion that best performance is achieved when read buffer is set to 8K.

Text file of 1 gigabyte is parsed within 130-140 seconds on my Core i5 2.6GHz.
