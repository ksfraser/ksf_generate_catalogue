<?php

/**
 * Base class for Generate Catalogue functionality
 * 
 * This file contains the BaseCatalogueGenerator class which provides common
 * functionality for file generation, email, and configuration management
 * for various catalogue export formats.
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat;

use Exception;

/**
 * Base class for Generate Catalogue functionality
 * 
 * Provides common functionality for file generation, email, and configuration
 * management for various catalogue export formats.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
abstract class BaseCatalogueGenerator
{
    /**
     * Whether to include headers in output files
     * 
     * @var bool
     * @since 1.0.0
     */
    protected $include_header;

    /**
     * Maximum rows allowed in a single file
     * 
     * @var int
     * @since 1.0.0
     */
    protected $maxrowsallowed;

    /**
     * Last order ID that was exported
     * 
     * @var int
     * @since 1.0.0
     */
    protected $lastoid;

    /**
     * Email address to send generated files to
     * 
     * @var string
     * @since 1.0.0
     */
    protected $mailto;

    /**
     * Email address to send files from
     * 
     * @var string
     * @since 1.0.0
     */
    protected $mailfrom;

    /**
     * Database connection reference
     * 
     * @var mixed
     * @since 1.0.0
     */
    protected $db;

    /**
     * Environment identifier (devel/accept/prod)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $environment;

    /**
     * Maximum number of pictures to process
     * 
     * @var int
     * @since 1.0.0
     */
    protected $maxpics;

    /**
     * Debug level (0=off, 1+=on with verbosity)
     * 
     * @var int
     * @since 1.0.0
     */
    protected $debug;

    /**
     * Array of field configurations
     * 
     * @var array
     * @since 1.0.0
     */
    protected $fields_array;

    /**
     * File writer instance
     * 
     * @var mixed
     * @since 1.0.0
     */
    protected $write_file;

    /**
     * Temporary directory for file storage
     * 
     * @var string
     * @since 1.0.0
     */
    protected $tmp_dir;

    /**
     * Output filename
     * 
     * @var string
     * @since 1.0.0
     */
    protected $filename;

    /**
     * Whether to generate labels (0/1)
     * 
     * @var int
     * @since 1.0.0
     */
    protected $dolabels;

    /**
     * Delivery number for order processing
     * 
     * @var int
     * @since 1.0.0
     */
    protected $delivery_no;

    /**
     * SMTP server hostname
     * 
     * @var string
     * @since 1.0.0
     */
    protected $smtp_server;

    /**
     * SMTP server port
     * 
     * @var int
     * @since 1.0.0
     */
    protected $smtp_port;

    /**
     * SMTP username
     * 
     * @var string
     * @since 1.0.0
     */
    protected $smtp_user;

    /**
     * SMTP password
     * 
     * @var string
     * @since 1.0.0
     */
    protected $smtp_pass;

    /**
     * Whether to send files by email
     * 
     * @var bool
     * @since 1.0.0
     */
    protected $b_email;

    /**
     * Sale price type identifier
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SALEPRICE_type;

    /**
     * Regular retail price type identifier
     * 
     * @var string
     * @since 1.0.0
     */
    protected $RETAIL_type;
    
    /**
     * Sale start date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SALE_START_DATE;
    
    /**
     * Sale end date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SALE_END_DATE;
    
    /**
     * Clearance sale start date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CLEARANCE_SALE_START_DATE;
    
    /**
     * Clearance sale end date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CLEARANCE_SALE_END_DATE;
    
    /**
     * Label text for clearance items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CLEARANCE_LABEL;
    
    /**
     * Prefix character for clearance items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CLEARANCE_PREFIX;
    
    /**
     * Category names for clearance items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CLEARANCE_CATEGORIES;
    
    /**
     * Discontinued sale start date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $DISCONTINUED_SALE_START_DATE;
    
    /**
     * Discontinued sale end date (YYYY-MM-DD format)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $DISCONTINUED_SALE_END_DATE;
    
    /**
     * Label text for discontinued items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $DISCONTINUED_LABEL;
    
    /**
     * Prefix character for discontinued items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $DISCONTINUED_PREFIX;
    
    /**
     * Category names for discontinued items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $DISCONTINUED_CATEGORIES;
    
    /**
     * Label text for special order items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SPECIAL_ORDER_LABEL;
    
    /**
     * Prefix character for special order items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SPECIAL_ORDER_PREFIX;
    
    /**
     * Category names for special order items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SPECIAL_ORDER_CATEGORIES;
    
    /**
     * Label text for custom items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CUSTOM_LABEL;
    
    /**
     * Prefix character for custom items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CUSTOM_PREFIX;
    
    /**
     * Category names for custom items
     * 
     * @var string
     * @since 1.0.0
     */
    protected $CUSTOM_CATEGORIES;
    
    /**
     * Primary location code for inventory
     * 
     * @var string
     * @since 1.0.0
     */
    protected $PRIMARY_LOC;
    
    /**
     * Secondary location code for inventory
     * 
     * @var string
     * @since 1.0.0
     */
    protected $SECONDARY_LOC;
    
    /**
     * Maximum rows per output file
     * 
     * @var int
     * @since 1.0.0
     */
    protected $max_rows_file;
    
    /**
     * Current file counter for multi-file output
     * 
     * @var int
     * @since 1.0.0
     */
    protected $file_count;
    
    /**
     * Base filename for output files
     * 
     * @var string
     * @since 1.0.0
     */
    protected $file_base;
    
    /**
     * File extension for output files
     * 
     * @var string
     * @since 1.0.0
     */
    protected $file_ext;
    
    /**
     * Sort order for output (price/stock)
     * 
     * @var string
     * @since 1.0.0
     */
    protected $sort_by;
    
    /**
     * Specific stock ID to process
     * 
     * @var string
     * @since 1.0.0
     */
    protected $stock_id;
    
    /**
     * Whether using thermal printer (affects barcode format)
     * 
     * @var bool
     * @since 1.0.0
     */
    protected $thermal_printer;
    
    /**
     * Whether to use price change dates for sales dates
     * 
     * @var bool
     * @since 1.0.0
     */
    protected $use_price_change_for_sales;
    
    /**
     * Test date for development/testing
     * 
     * @var string
     * @since 1.0.0
     */
    protected $TEST_DATE;
    
    /**
     * Preferences table name
     * 
     * @var string
     * @since 1.0.0
     */
    protected $prefs_tablename;
    
    /**
     * SQL query for data retrieval
     * 
     * @var string
     * @since 1.0.0
     */
    protected $query;
    
    /**
     * CSV header line
     * 
     * @var string
     * @since 1.0.0
     */
    protected $hline;
    
    /**
     * Database interface instance
     * 
     * @var DatabaseInterface|null
     * @since 1.0.0
     */
    protected $database;

    /**
     * Constructor - Initialize the catalogue generator
     * 
     * @param string $prefs_tablename Name of the preferences table
     * 
     * @since 1.0.0
     */
    public function __construct($prefs_tablename)
    {
        global $db;
        $this->db = $db;
        $this->prefs_tablename = $prefs_tablename;
        
        $this->tmp_dir = "../../tmp";
        $this->filename = "catalogue.csv";
        $this->file_count = 0;
        $this->file_ext = "csv";
        $this->dolabels = 0;
        
        $this->initializeDefaults();
    }

    /**
     * Set the database interface
     * 
     * @param DatabaseInterface $database Database interface instance
     * 
     * @return void
     * @since 1.0.0
     */
    public function setDatabase(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Get the database interface, creating a default one if not set
     * 
     * @return DatabaseInterface Database interface instance
     * @since 1.0.0
     */
    protected function getDatabase()
    {
        if ($this->database === null) {
            $this->database = new FrontAccountingDatabase();
        }
        return $this->database;
    }

    /**
     * Initialize default configuration values
     * 
     * @return void
     * @since 1.0.0
     */
    protected function initializeDefaults()
    {
        $this->include_header = true;
        $this->RETAIL_type = "Retail";
        $this->SALEPRICE_type = "Sale";
        $this->PRIMARY_LOC = "HG";
        $this->SECONDARY_LOC = "HOLD";
    }

    /**
     * Set the filename for the export file, taking file_count into consideration
     * 
     * @return void
     * @since 1.0.0
     */
    public function setFileName()
    {
        $this->filename = $this->file_base . "_" . $this->file_count . "." . $this->file_ext;
    }

    /**
     * Prepare an output file for writing
     * 
     * @throws Exception if tmp_dir or filename not set
     * @return void
     * @since 1.0.0
     */
    public function prepWriteFile()
    {
        if (!isset($this->tmp_dir) || strlen($this->tmp_dir) < 3) {
            throw new Exception("Tmp dir not set");
        }
        if (!isset($this->filename) || strlen($this->filename) < 3) {
            throw new Exception("Output filename not set");
        }
        
        try {
            // For now, we'll assume the write_file class exists in the global namespace
            // This will need to be refactored when the ksf-file library is created
            require_once('../ksf_modules_common/class.write_file.php');
            $this->write_file = new \write_file($this->tmp_dir, $this->filename);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Email the generated file
     * 
     * @param string $email_subject Subject line for the email
     * 
     * @return bool True if email sent, false otherwise
     * @since 1.0.0
     */
    public function emailFile($email_subject = 'Catalogue file')
    {
        if (!isset($this->mailto)) {
            return false;
        }

        if ($this->b_email) {
            try {
                // For now, we'll assume the email_file class exists in the global namespace
                require_once('../ksf_modules_common/class.email_file.php');
                $mail_file = new \email_file(
                    $this->mailfrom, 
                    $this->mailto, 
                    $this->tmp_dir, 
                    $this->filename, 
                    $this->smtp_user, 
                    $this->smtp_pass, 
                    $this->smtp_server, 
                    $this->smtp_port
                );
                $mail_file->email_file($email_subject);
                if (function_exists('display_notification')) {
                    display_notification("Email sent to {$this->mailto}.");
                }
                return true;
            } catch (Exception $e) {
                if (function_exists('display_notification')) {
                    display_notification("Email failed: " . $e->getMessage());
                }
            }
        }
        return false;
    }

    /**
     * Abstract method to create the catalogue file
     * Must be implemented by concrete classes
     * 
     * @return int Number of rows processed
     * @since 1.0.0
     */
    abstract public function createFile();

    // Getters and setters for configuration
    
    /**
     * Set the retail price type
     * 
     * @param string $type Retail price type identifier
     * 
     * @return void
     * @since 1.0.0
     */
    public function setRetailType($type) { $this->RETAIL_type = $type; }
    
    /**
     * Set the sale price type
     * 
     * @param string $type Sale price type identifier
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSalePriceType($type) { $this->SALEPRICE_type = $type; }
    
    /**
     * Set the sale start date
     * 
     * @param string $date Sale start date in YYYY-MM-DD format
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSaleStartDate($date) { $this->SALE_START_DATE = $date; }
    
    /**
     * Set the sale end date
     * 
     * @param string $date Sale end date in YYYY-MM-DD format
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSaleEndDate($date) { $this->SALE_END_DATE = $date; }
    
    /**
     * Set the primary location code
     * 
     * @param string $loc Primary location code
     * 
     * @return void
     * @since 1.0.0
     */
    public function setPrimaryLocation($loc) { $this->PRIMARY_LOC = $loc; }
    
    /**
     * Set the secondary location code
     * 
     * @param string $loc Secondary location code
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSecondaryLocation($loc) { $this->SECONDARY_LOC = $loc; }
    
    /**
     * Set maximum rows per file
     * 
     * @param int $rows Maximum rows per output file
     * 
     * @return void
     * @since 1.0.0
     */
    public function setMaxRowsPerFile($rows) { $this->max_rows_file = $rows; }
    
    /**
     * Set the temporary directory path
     * 
     * @param string $dir Temporary directory path
     * 
     * @return void
     * @since 1.0.0
     */
    public function setTmpDir($dir) { $this->tmp_dir = $dir; }
    
    /**
     * Set the output filename
     * 
     * @param string $name Output filename
     * 
     * @return void
     * @since 1.0.0
     */
    public function setOutputFilename($name) { $this->filename = $name; }
    
    /**
     * Set the email recipient address
     * 
     * @param string $email Email address to send files to
     * 
     * @return void
     * @since 1.0.0
     */
    public function setEmailTo($email) { $this->mailto = $email; }
    
    /**
     * Set the email sender address
     * 
     * @param string $email Email address to send files from
     * 
     * @return void
     * @since 1.0.0
     */
    public function setEmailFrom($email) { $this->mailfrom = $email; }
    
    /**
     * Set the sort order
     * 
     * @param string $sort Sort order (price/stock)
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSortBy($sort) { $this->sort_by = $sort; }
    
    /**
     * Set the specific stock ID to process
     * 
     * @param string $stock_id Stock ID to process
     * 
     * @return void
     * @since 1.0.0
     */
    public function setStockId($stock_id) { $this->stock_id = $stock_id; }
    
    /**
     * Set whether using thermal printer
     * 
     * @param bool $thermal True for thermal printer, false for standard
     * 
     * @return void
     * @since 1.0.0
     */
    public function setThermalPrinter($thermal) { $this->thermal_printer = $thermal; }
    
    /**
     * Set the delivery number
     * 
     * @param int $delivery_no Delivery number
     * 
     * @return void
     * @since 1.0.0
     */
    public function setDeliveryNo($delivery_no) { $this->delivery_no = $delivery_no; }
    
    /**
     * Set whether to send files by email
     * 
     * @param bool $b_email True to send by email, false otherwise
     * 
     * @return void
     * @since 1.0.0
     */
    public function setB_email($b_email) { $this->b_email = $b_email; }
    
    /**
     * Set SMTP server hostname
     * 
     * @param string $server SMTP server hostname
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSmtpServer($server) { $this->smtp_server = $server; }
    
    /**
     * Set SMTP server port
     * 
     * @param int $port SMTP server port number
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSmtpPort($port) { $this->smtp_port = $port; }
    
    /**
     * Set SMTP username
     * 
     * @param string $user SMTP username
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSmtpUser($user) { $this->smtp_user = $user; }
    
    /**
     * Set SMTP password
     * 
     * @param string $pass SMTP password
     * 
     * @return void
     * @since 1.0.0
     */
    public function setSmtpPass($pass) { $this->smtp_pass = $pass; }
    
    /**
     * Set debug level
     * 
     * @param int $debug Debug level (0=off, 1+=on with verbosity)
     * 
     * @return void
     * @since 1.0.0
     */
    public function setDebug($debug) { $this->debug = $debug; }

    /**
     * Get the retail price type
     * 
     * @return string Retail price type identifier
     * @since 1.0.0
     */
    public function getRetailType() { return $this->RETAIL_type; }
    
    /**
     * Get the sale price type
     * 
     * @return string Sale price type identifier
     * @since 1.0.0
     */
    public function getSalePriceType() { return $this->SALEPRICE_type; }
    
    /**
     * Get the current filename
     * 
     * @return string Current filename
     * @since 1.0.0
     */
    public function getFilename() { return $this->filename; }
}
