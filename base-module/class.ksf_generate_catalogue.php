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

    function write_file_form()
    {
        if( $this->dolabels)
            $this->call_table( 'gencat', "Create Catalogue File and Labels" );
        else
            $this->call_table( 'gencat', "Create Catalogue File" );
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
