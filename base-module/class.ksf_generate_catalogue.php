<?php

require_once( '../ksf_modules_common/class.generic_fa_interface.php' );

/************************************************************************//**
 * KSF Generate Catalogue - Refactored Base Module
 *
 * This class now acts as a bridge between FrontAccounting and the composer library
 * Most of the heavy lifting has been moved to the composer library classes
 *
 * *************************************************************************/
class ksf_generate_catalogue extends generic_fa_interface
{
    var $include_header;
    var $maxrowsallowed;
    var $lastoid;
    var $mailto;
    var $mailfrom;
    var $db;
    var $environment;
    var $maxpics;
    var $debug;
    var $fields_array;
    var $write_file;
    var $tmp_dir;
    var $filename;
    var $dolabels;
    var $delivery_no;
    var $smtp_server;
    var $smtp_port;
    var $smtp_user;
    var $smtp_pass;
    var $b_email;
    protected $SALEPRICE_type;
    protected $RETAIL_type;
    var $SALE_START_DATE;
    var $SALE_END_DATE;
    var $CLEARANCE_SALE_START_DATE;
    var $CLEARANCE_SALE_END_DATE;
    var $CLEARANCE_LABEL;
    var $CLEARANCE_PREFIX;
    var $CLEARANCE_CATEGORIES;
    var $DISCONTINUED_SALE_START_DATE;
    var $DISCONTINUED_SALE_END_DATE;
    var $DISCONTINUED_LABEL;
    var $DISCONTINUED_PREFIX;
    var $DISCONTINUED_CATEGORIES;
    var $SPECIAL_ORDER_LABEL;
    var $SPECIAL_ORDER_PREFIX;
    var $SPECIAL_ORDER_CATEGORIES;
    var $CUSTOM_LABEL;
    var $CUSTOM_PREFIX;
    var $CUSTOM_CATEGORIES;
    var $PRIMARY_LOC;
    var $SECONDARY_LOC;
    var $max_rows_file;
    var $file_count;
    var $file_base;
    var $file_ext;
    var $sort_by;
    protected $stock_id;
    var $thermal_printer;
    var $use_price_change_for_sales;
    protected $TEST_DATE;
    private $catalogueFactory;

    function __construct( $prefs_tablename )
    {
        simple_page_mode(true);
        global $db;
        $this->db = $db;
        parent::__construct( null, null, null, null, $prefs_tablename );
        
        // Initialize the composer library factory
        $this->initializeCatalogueFactory();
        
        $this->tmp_dir = "../../tmp";
        $this->filename = "pricebook.csv";
        $this->set_var( 'include_header', TRUE );
        
        $this->initializeConfigValues();
        $this->initializeTabs();
        
        $this->dolabels = 0;
        $this->file_count = 0;
        $this->file_ext = "csv";
        
        $this->add_submodules();
    }

    /**
     * Initialize the catalogue generator factory with composer library
     */
    private function initializeCatalogueFactory()
    {
        // Check if composer autoloader is available
        $autoloadPaths = [
            __DIR__ . '/vendor/autoload.php',
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php'
        ];
        
        $composerLoaded = false;
        foreach ($autoloadPaths as $autoloadPath) {
            if (file_exists($autoloadPath)) {
                require_once $autoloadPath;
                $composerLoaded = true;
                break;
            }
        }
        
        if ($composerLoaded && class_exists('Ksfraser\\Frontaccounting\\GenCat\\CatalogueGeneratorFactory')) {
            $database = new \Ksfraser\Frontaccounting\GenCat\FrontAccountingDatabase();
            $this->catalogueFactory = new \Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory(
                $database, 
                $this->prefs_tablename
            );
        }
    }

    /**
     * Initialize configuration values
     */
    private function initializeConfigValues()
    {
        $this->config_values[] = array( 'pref_name' => 'lastoid', 'label' => 'Last Order Exported' );
        $this->config_values[] = array( 'pref_name' => 'include_header', 'label' => 'Include Headers' );
        $this->config_values[] = array( 'pref_name' => 'maxrowsallowed', 'label' => 'Maximum Rows Allowed in file' );
        $this->config_values[] = array( 'pref_name' => 'mailto', 'label' => 'Mail CSV to email address' );
        $this->config_values[] = array( 'pref_name' => 'mailfrom', 'label' => 'Mail from email address' );
        $this->config_values[] = array( 'pref_name' => 'environment', 'label' => 'Environment (devel/accept/prod)' );
        $this->config_values[] = array( 'pref_name' => 'dolabels', 'label' => 'Print Labels (0/1)' );
        $this->config_values[] = array( 'pref_name' => 'smtp_server', 'label' => 'Mail Server' );
        $this->config_values[] = array( 'pref_name' => 'smtp_port', 'label' => 'Mail Server Port (25/993)' );
        $this->config_values[] = array( 'pref_name' => 'smtp_user', 'label' => 'Mail Server User' );
        $this->config_values[] = array( 'pref_name' => 'smtp_pass', 'label' => 'Mail Server Password' );
        $this->config_values[] = array( 'pref_name' => 'b_email', 'label' => 'Send file by email', 'type' => 'yesno_list' );
        $this->config_values[] = array( 'pref_name' => 'debug', 'label' => 'Debug (0,1+)' );
        $this->config_values[] = array( 'pref_name' => 'PRIMARY_LOC', 'label' => 'Primary Retail Location for Inventory string (default HG)', 'type' => 'location' );
        $this->config_values[] = array( 'pref_name' => 'SECONDARY_LOC', 'label' => 'Secondary Retail Location for Inventory string (default HOLD)', 'type' => 'location' );
        $this->config_values[] = array( 'pref_name' => 'RETAIL_type', 'label' => 'Retail Pricebook (sales_type_id) string' );
        $this->config_values[] = array( 'pref_name' => 'SALEPRICE_type', 'label' => 'Sales Pricing Pricebook (sales_type_id) string' );
        $this->config_values[] = array( 'pref_name' => 'SALE_START_DATE', 'label' => 'Sale Start Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'SALE_END_DATE', 'label' => 'Sale End Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'DISCONTINUED_SALE_START_DATE', 'label' => 'Discontinued Sale Start Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'DISCONTINUED_SALE_END_DATE', 'label' => 'Discontinued Sale End Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'DISCONTINUED_LABEL', 'label' => 'Discontinued Label on products e.g. --DISCONTINUED' );
        $this->config_values[] = array( 'pref_name' => 'DISCONTINUED_PREFIX', 'label' => 'Discontinued Prefix Character e.g. ~' );
        $this->config_values[] = array( 'pref_name' => 'DISCONTINUED_CATEGORIES', 'label' => 'Discontinued Categories e.g. discontinued' );
        $this->config_values[] = array( 'pref_name' => 'CLEARANCE_SALE_START_DATE', 'label' => 'Clearance Sale Start Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'CLEARANCE_SALE_END_DATE', 'label' => 'Clearance Sale End Date YYYY-MM-DD' );
        $this->config_values[] = array( 'pref_name' => 'CLEARANCE_LABEL', 'label' => 'Clearance Label on products e.g. --CLEARANCE' );
        $this->config_values[] = array( 'pref_name' => 'CLEARANCE_PREFIX', 'label' => 'Clearance Prefix Character e.g. ^' );
        $this->config_values[] = array( 'pref_name' => 'CLEARANCE_CATEGORIES', 'label' => 'Clearance Categories e.g. clearance' );
        $this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_LABEL', 'label' => 'Special Order Label on products e.g. --SPECIAL_ORDER' );
        $this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_PREFIX', 'label' => 'Special Order Prefix Character e.g. *' );
        $this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_CATEGORIES', 'label' => 'Special Order Categories e.g. specialorder' );
        $this->config_values[] = array( 'pref_name' => 'CUSTOM_LABEL', 'label' => 'Custom Order Label on products e.g. --CUSTOM' );
        $this->config_values[] = array( 'pref_name' => 'CUSTOM_PREFIX', 'label' => 'Custom Order Prefix Character e.g. #' );
        $this->config_values[] = array( 'pref_name' => 'CUSTOM_CATEGORIES', 'label' => 'Special Order Categories e.g. customorder' );
        $this->config_values[] = array( 'pref_name' => 'max_rows_file', 'label' => 'Maximum rows per file' );
        $this->config_values[] = array( 'pref_name' => 'thermal_printer', 'label' => ' Are we using a thermal printer (T) or Avery Labels with 3of9(F) (bool)' );
    }

    /**
     * Initialize tabs/actions
     */
    private function initializeTabs()
    {
        $this->tabs[] = array( 'title' => 'Config Updated', 'action' => 'update', 'form' => 'checkprefs', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'Configuration', 'action' => 'config', 'form' => 'action_show_form', 'hidden' => FALSE );
        $this->tabs[] = array( 'title' => 'Install Module', 'action' => 'create', 'form' => 'install', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'Export File', 'action' => 'exportfile', 'form' => 'write_file_form', 'hidden' => FALSE );
        $this->tabs[] = array( 'title' => 'Generate Catalogue', 'action' => 'gencat', 'form' => 'form_pricebook', 'hidden' => TRUE );
        
        // Add individual generator tabs
        $this->tabs[] = array( 'title' => 'Pricebook Generated', 'action' => 'gen_pricebook', 'form' => 'form_individual_pricebook', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'Square Catalog Generated', 'action' => 'gen_square', 'form' => 'form_individual_square', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'WooCommerce Import Generated', 'action' => 'gen_woocommerce', 'form' => 'form_individual_woocommerce', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'WooPOS Count Generated', 'action' => 'gen_woopos', 'form' => 'form_individual_woopos', 'hidden' => TRUE );
        
        $this->tabs[] = array( 'title' => 'Labels for a Purchase Order', 'action' => 'polabelsfile', 'form' => 'polabelsfile_form', 'hidden' => FALSE );
        $this->tabs[] = array( 'title' => 'Labels Generated', 'action' => 'label_export_by_PO_Delivery', 'form' => 'label_export_by_PO_Delivery', 'hidden' => TRUE );
        $this->tabs[] = array( 'title' => 'Labels for a Stock_id (SKU)', 'action' => 'skulabelsfile', 'form' => 'skulabelsfile_form', 'hidden' => FALSE );
        $this->tabs[] = array( 'title' => 'Label for a Stock_id (SKU)', 'action' => 'skulabelsfile_done', 'form' => 'skulabelsfile_done', 'hidden' => TRUE );
    }

    /**
     * Set the filename for the export file. Take file_count into consideration
     */
    function setFileName()
    {
        $this->filename = $this->file_base . "_" . $this->file_count . "." . $this->file_ext;
    }

    /**
     * Prepare an output file.
     */
    function prep_write_file()
    {	
        if( ! isset( $this->tmp_dir ) OR strlen( $this->tmp_dir ) < 3 )
            throw new Exception( "Tmp dir not set" );
        if( ! isset( $this->filename ) OR strlen( $this->filename ) < 3 )
            throw new Exception( "Output filename not set" );
        try 
        {
            require_once( '../ksf_modules_common/class.write_file.php' ); 
            $this->write_file = new write_file( $this->tmp_dir, $this->filename );
        }
        catch( Exception $e )
        {
            throw $e;
        }
    }

    /**
     * Create price book using composer library if available, fallback to legacy classes
     */
    function create_price_book()
    {
        $config = $this->getConfigArray();
        $rowcount = 0;

        if ($this->catalogueFactory) {
            // Use composer library
            try {
                $pb = $this->catalogueFactory->createPricebookFile($config);
                $rowcount += $pb->createFile();

                $sc = $this->catalogueFactory->createSquareCatalog($config);
                $rowcount += $sc->createFile();

                $woo = $this->catalogueFactory->createWoocommerceImport($config);
                $rowcount += $woo->createFile();

                $woopos = $this->catalogueFactory->createWooPOSCount($config);
                $rowcount += $woopos->createFile();

            } catch (Exception $e) {
                display_notification("Error using composer library: " . $e->getMessage());
                return $this->createPriceBookLegacy();
            }
        } else {
            // Fallback to legacy classes
            return $this->createPriceBookLegacy();
        }

        return $rowcount;
    }

    /**
     * Legacy price book creation (fallback)
     */
    private function createPriceBookLegacy()
    {
        $rowcount = 0;
        
        if( include_once( 'class.pricebook_file.php' ) )
        {
            $pb = new pricebook_file( $this->prefs_tablename );
            foreach( $this->config_values as $arr )
            {
                $value = $arr["pref_name"];
                $pb->$value = $this->$value;
            }
            $rowcount += $pb->create_file();
        }
        if( include_once( 'class.square_catalog.php' ) )
        {
            $sc = new square_catalog( $this->prefs_tablename );
            foreach( $this->config_values as $arr )
            {
                $value = $arr["pref_name"];
                $sc->$value = $this->$value;
            }
            $rowcount += $sc->create_file();
        }
        if( include_once( 'class.woocommerce_import.php' ) )
        {
            $sc = new woocommerce_import( $this->prefs_tablename );
            foreach( $this->config_values as $arr )
            {
                $value = $arr["pref_name"];
                $sc->$value = $this->$value;
            }
            $sc->setQuery();
            $rowcount += $sc->create_file();
        }
        if( include_once( 'class.WooPOS_Count.php' ) )
        {
            $woopos = new WooPOS_Count_file( $this->prefs_tablename );
            foreach( $this->config_values as $arr )
            {
                $value = $arr["pref_name"];
                $woopos->$value = $this->$value;
            }
            $woopos->create_file();
        }
        
        return $rowcount;
    }

    /**
     * Create SKU labels using composer library if available, fallback to legacy
     */
    function create_sku_labels()
    {
        $config = $this->getConfigArray();

        if ($this->catalogueFactory) {
            try {
                $lf = $this->catalogueFactory->createLabelsFile($config);
                if (isset($this->stock_id)) {
                    $lf->setStockId($this->stock_id);
                    $rowcount = $lf->createSkuLabelsFromSku();
                } else {
                    $rowcount = $lf->createFile();
                }
                return $rowcount;
            } catch (Exception $e) {
                display_notification("Error using composer library for labels: " . $e->getMessage());
            }
        }
        
        // Fallback to legacy
        return $this->createSkuLabelsLegacy();
    }

    /**
     * Legacy SKU labels creation (fallback)
     */
    private function createSkuLabelsLegacy()
    {
        if( include_once( 'class.labels_file.php' ) )
        {
            $lf = new labels_file( $this->prefs_tablename );
            foreach( $this->config_values as $arr )
            {
                $value = $arr["pref_name"];
                $lf->$value = $this->$value;
            }
            if( isset( $this->stock_id ) )
            {
                $lf->set( "stock_id", $this->stock_id );
            }
            $rowcount = $lf->create_file();
            $lf->email_file();
            return $rowcount;
        }
        else
            return -1;
    }

    /**
     * Get available catalogue generators dynamically
     * 
     * @return array Array of generator information with keys: 'name', 'title', 'action', 'method'
     */
    private function getAvailableGenerators()
    {
        // If composer factory is available, use its dynamic list
        if ($this->catalogueFactory) {
            try {
                $factoryGenerators = $this->catalogueFactory->getAvailableGenerators();
                $generators = [];
                
                foreach ($factoryGenerators as $gen) {
                    // Skip labels as it's handled separately
                    if ($gen['name'] !== 'labels') {
                        $generators[] = [
                            'name' => $gen['name'],
                            'title' => $gen['title'],
                            'action' => 'gen_' . $gen['name'],
                            'method' => $gen['method'],
                            'description' => $gen['description']
                        ];
                    }
                }
                
                return $generators;
            } catch (Exception $e) {
                // Fall back to static list if factory method fails
            }
        }
        
        // Static fallback list for when composer library is not available
        return [
            [
                'name' => 'pricebook',
                'title' => 'Pricebook File',
                'action' => 'gen_pricebook', 
                'method' => 'createPricebookFile',
                'description' => 'Generate pricebook CSV file'
            ],
            [
                'name' => 'square',
                'title' => 'Square Catalog',
                'action' => 'gen_square',
                'method' => 'createSquareCatalog', 
                'description' => 'Generate Square catalog import file'
            ],
            [
                'name' => 'woocommerce', 
                'title' => 'WooCommerce Import',
                'action' => 'gen_woocommerce',
                'method' => 'createWoocommerceImport',
                'description' => 'Generate WooCommerce product import CSV'
            ],
            [
                'name' => 'woopos',
                'title' => 'WooPOS Count',
                'action' => 'gen_woopos',
                'method' => 'createWooPOSCount',
                'description' => 'Generate WooPOS inventory count file'
            ]
        ];
    }

    /**
     * Create a single generator file by type
     *
     * @param string $generatorType The type of generator to create
     * @return int Number of rows created
     */
    private function createSingleGenerator($generatorType)
    {
        $config = $this->getConfigArray();
        $rowcount = 0;
        
        if ($this->catalogueFactory) {
            try {
                switch ($generatorType) {
                    case 'pricebook':
                        $generator = $this->catalogueFactory->createPricebookFile($config);
                        break;
                    case 'square':
                        $generator = $this->catalogueFactory->createSquareCatalog($config);
                        break;
                    case 'woocommerce':
                        $generator = $this->catalogueFactory->createWoocommerceImport($config);
                        break;
                    case 'woopos':
                        $generator = $this->catalogueFactory->createWooPOSCount($config);
                        break;
                    default:
                        throw new Exception("Unknown generator type: $generatorType");
                }
                
                $rowcount = $generator->createFile();
                $this->email_file("Generated $generatorType file");
                
            } catch (Exception $e) {
                display_notification("Error generating $generatorType: " . $e->getMessage());
                return $this->createSingleGeneratorLegacy($generatorType);
            }
        } else {
            return $this->createSingleGeneratorLegacy($generatorType);
        }
        
        return $rowcount;
    }

    /**
     * Create single generator using legacy classes (fallback)
     *
     * @param string $generatorType The type of generator to create
     * @return int Number of rows created
     */
    private function createSingleGeneratorLegacy($generatorType)
    {
        $rowcount = 0;
        
        switch ($generatorType) {
            case 'pricebook':
                if (include_once('class.pricebook_file.php')) {
                    $pb = new pricebook_file($this->prefs_tablename);
                    $this->applyConfigToLegacyClass($pb);
                    $rowcount = $pb->create_file();
                }
                break;
                
            case 'square':
                if (include_once('class.square_catalog.php')) {
                    $sc = new square_catalog($this->prefs_tablename);
                    $this->applyConfigToLegacyClass($sc);
                    $rowcount = $sc->create_file();
                }
                break;
                
            case 'woocommerce':
                if (include_once('class.woocommerce_import.php')) {
                    $wc = new woocommerce_import($this->prefs_tablename);
                    $this->applyConfigToLegacyClass($wc);
                    $wc->setQuery();
                    $rowcount = $wc->create_file();
                }
                break;
                
            case 'woopos':
                if (include_once('class.WooPOS_Count.php')) {
                    $woopos = new WooPOS_Count_file($this->prefs_tablename);
                    $this->applyConfigToLegacyClass($woopos);
                    $rowcount = $woopos->create_file();
                }
                break;
        }
        
        return $rowcount;
    }

    /**
     * Apply configuration to legacy class instances
     *
     * @param object $classInstance The legacy class instance
     */
    private function applyConfigToLegacyClass($classInstance)
    {
        foreach ($this->config_values as $arr) {
            $value = $arr["pref_name"];
            if (isset($this->$value)) {
                $classInstance->$value = $this->$value;
            }
        }
    }

    /**
     * Get configuration as array for composer library
     */
    private function getConfigArray()
    {
        $config = [];
        foreach ($this->config_values as $arr) {
            $pref_name = $arr["pref_name"];
            if (isset($this->$pref_name)) {
                $config[$pref_name] = $this->$pref_name;
            }
        }
        return $config;
    }
    
    /**
     * Email file functionality
     */
    function email_file( $email_subject = 'Pricebook file' )
    {
        if( isset( $this->mailto ) )
        {
            require_once( '../ksf_modules_common/class.email_file.php' ); 
            if( $this->b_email )
            {
                try {
                    $mail_file = new email_file( $this->mailfrom, $this->mailto, $this->tmp_dir, $this->filename, $this->smtp_user, $this->smtp_pass, $this->smtp_server, $this->smtp_port );
                    $mail_file->email_file( $email_subject );
                    display_notification("email sent to $this->mailto.");
                }
                catch( Exception $e )
                {
                }
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Form processing methods - these remain largely unchanged from the original
     */
    function form_pricebook()
    {
        $this->create_price_book();
        $this->email_file();
        if( $this->dolabels )
        {
            $this->create_sku_labels();
        }
        $this->call_table( '', "OK" );
    }

    /**
     * Individual generator form handlers
     */
    function form_individual_pricebook()
    {
        $rowcount = $this->createSingleGenerator('pricebook');
        display_notification("Pricebook file generated with $rowcount rows.");
        $this->call_table('', "Pricebook Generated Successfully");
    }
    
    function form_individual_square()
    {
        $rowcount = $this->createSingleGenerator('square');
        display_notification("Square catalog generated with $rowcount rows.");
        $this->call_table('', "Square Catalog Generated Successfully");
    }
    
    function form_individual_woocommerce()
    {
        $rowcount = $this->createSingleGenerator('woocommerce');
        display_notification("WooCommerce import file generated with $rowcount rows.");
        $this->call_table('', "WooCommerce Import Generated Successfully");
    }
    
    function form_individual_woopos()
    {
        $rowcount = $this->createSingleGenerator('woopos');
        display_notification("WooPOS count file generated with $rowcount rows.");
        $this->call_table('', "WooPOS Count Generated Successfully");
    }

    function write_file_form()
    {
        // Start form for catalogue generation options
        start_form(true);
        start_table(TABLESTYLE2, "width=60%");
        table_section_title("Generate Catalogue Files");
        
        // Description row
        label_row("Choose your generation option:", "Generate all files at once or individual files:");
        
        end_table(1);
        
        // Generate All Files section
        start_table(TABLESTYLE2, "width=60%");
        table_section_title("Generate All Files");
        
        if ($this->dolabels) {
            label_row("", "This will create all catalogue files and labels");
            hidden('action', 'gencat');
            submit_center('gencat', "Create All Catalogue Files and Labels");
        } else {
            label_row("", "This will create all catalogue files (Pricebook, Square, WooCommerce, WooPOS)");
            hidden('action', 'gencat'); 
            submit_center('gencat', "Create All Catalogue Files");
        }
        
        end_table(1);
        end_form();
        
        // Individual Files section
        start_form(true);
        start_table(TABLESTYLE2, "width=60%");
        table_section_title("Generate Individual Files");
        
        $generators = $this->getAvailableGenerators();
        foreach ($generators as $generator) {
            $buttonText = "Generate " . $generator['title'];
            $description = $generator['description'];
            
            label_row($generator['title'] . ":", $description);
            
            // Create individual form for each generator
            echo "<tr><td colspan='2' align='center'>";
            echo "<form method='post'>";
            echo "<input type='hidden' name='action' value='" . $generator['action'] . "' />";
            echo "<input type='submit' name='" . $generator['name'] . "_btn' value='$buttonText' class='inputsubmit' />";
            echo "</form>";
            echo "</td></tr>";
        }
        
        end_table(1);
        end_form();
    }

    function skulabelsfile_form()
    {
        $selected_id = 1;
        $none_option = "";
        $submit_on_change = FALSE;
        $all = FALSE;
        $all_items = TRUE;
        $mode = 1;
        $spec_option = "";
        start_form(true);
        start_table(TABLESTYLE2, "width=40%");
        table_section_title("Labels by Stock_ID");
        text_row("SKU to generate the label", 'stock_id', "", STOCK_ID_LENGTH, STOCK_ID_LENGTH);
        end_table(1);
        hidden('action', 'skulabelsfile_done');
        submit_center('skulabelsfile', "Generate Label for SKU");
        end_form();
    }

    function skulabelsfile_done()
    {
        if( !isset( $this->stock_id ) )
        {
            if( isset( $_POST['stock_id'] ) )
                $this->stock_id = $_POST['stock_id'];
        }

        $config = $this->getConfigArray();
        
        if ($this->catalogueFactory) {
            try {
                $lf = $this->catalogueFactory->createLabelsFile($config);
                $lf->setStockId($this->stock_id);
                $count = $lf->createSkuLabelsFromSku();
                return TRUE;
            } catch (Exception $e) {
                display_notification("Error using composer library: " . $e->getMessage());
            }
        }
        
        // Fallback to legacy
        if( include_once( 'class.labels_file.php' ) )
        {
            $lf = new labels_file( $this->prefs_tablename );
            $lf->set( "stock_id", $this->stock_id );
            foreach( $this->config_values as $arr )
            {
                if( isset( $arr['title'] ) )
                {
                    foreach( $arr['title'] as $value )
                    {
                        $lf->$value = $this->$value;
                    }
                }
                else
                {
                    if( $this->debug > 1 )
                    {
                        echo "<br />";
                        var_dump( $arr );
                        echo "<br />";
                    }
                }
            }
            $count = $lf->create_sku_labels_from_sku();
        }
        return TRUE;
    }

    function polabelsfile_form()
    {
        $selected_id = 1;
        $none_option = "";
        $submit_on_change = FALSE;
        $all = FALSE;
        $all_items = TRUE;
        $mode = 1;
        $spec_option = "";
        start_form(true);
        start_table(TABLESTYLE2, "width=40%");
        table_section_title("Labels for Purchase Order");

        text_row("Export Purchase Order -- <b>Delivery ID</b> (First):", 'delivery_no', $this->lastoid+1, 10, 10);
        text_row("Export Purchase Order -- <b>Delivery ID</b> (Last):", 'last_delivery_no', $this->lastoid+1, 10, 10);

        end_table(1);

        hidden('action', 'label_export_by_PO_Delivery');
        submit_center('label_export_by_PO_Delivery', "Export PO Delivery");

        end_form();
    }

    function label_export_by_PO_Delivery()
    {
        if( !isset( $this->delivery_no ) )
        {
            if( isset( $_POST['delivery_no'] ) )
                $this->delivery_no = $_POST['delivery_no'];
        }

        $config = $this->getConfigArray();
        
        if ($this->catalogueFactory) {
            try {
                $lf = $this->catalogueFactory->createLabelsFile($config);
                $lf->setDeliveryNo($this->delivery_no);
                if (isset($_POST['last_delivery_no'])) {
                    $lf->setLastDeliveryNo($_POST['last_delivery_no']);
                    $this->set_pref('lastoid', $_POST['last_delivery_no']);
                } else {
                    $lf->setLastDeliveryNo($this->delivery_no);
                    $this->set_pref('lastoid', $this->delivery_no);
                }
                $count = $lf->createSkuLabelsFromPO($this->delivery_no);
                return TRUE;
            } catch (Exception $e) {
                display_notification("Error using composer library: " . $e->getMessage());
            }
        }
        
        // Fallback to legacy
        if( include_once( 'class.labels_file.php' ) )
        {
            $lf = new labels_file( $this->prefs_tablename );
            $lf->set( "delivery_no", $this->delivery_no );
            if( isset(  $_POST['last_delivery_no'] ) )
            {
                $lf->set( "last_delivery_no", $_POST['last_delivery_no'] );
            }
            else
            {
                $lf->set( "last_delivery_no", $this->delivery_no );
            }
            foreach( $this->config_values as $arr )
            {
                if( isset( $arr['title'] ) )
                {
                    foreach( $arr['title'] as $value )
                    {
                        $lf->$value = $this->$value;
                    }
                }
                else
                {
                    if( $this->debug > 1 )
                    {
                        echo "<br />";
                        var_dump( $arr );
                        echo "<br />";
                    }
                }
            }
            $count = $lf->create_sku_labels_from_PO( $this->delivery_no );
            if( 0 < $count )
            {
                if( isset( $this->last_delivery_no ) )
                {
                    $this->set_pref( 'lastoid', $this->last_delivery_no );
                }
                else
                {
                    $this->set_pref( 'lastoid', $this->delivery_no );
                }
            }
        }
        return TRUE;
    }
}

?>
