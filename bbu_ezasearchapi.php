<?php

define( 'BBUEZA_MAX_ARTICLES', 20 );
define( 'BBUEZA_NO_ARTICLES_DEFAULT', 5 );

if ( !class_exists( 'BBU_EzaSearchApi' ) ) {

	class BBU_EzaSearchApi {
		
		// URL to EzineArticles API
		var $bbuEzaApiUrl = 'http://api.ezinearticles.com/api.php';
		
		// The search options
		var $bbuEzaSearchValues = array( 'articles', 'articles.most.viewed', 'articles.most.recent', 'articles.most.published', 'articles.most.emailed' );
		
		// EzineArticles categories
		var $bbuEzaAllCategories;
		
	  // Options as saved in wp_options table
		var $bbuEzaOptions = array();

		// Read all options from wp_options table
		function bbuEzaReadOptions() {
			$this->bbuEzaOptions['bbuEzaApiKey'] = get_option( 'bbuEzaApiKey' );
			$this->bbuEzaOptions['bbuEzaAuthor'] = get_option( 'bbuEzaAuthor' );
			$this->bbuEzaOptions['bbuEzaSearch'] = get_option( 'bbuEzaSearch' );
			$this->bbuEzaOptions['bbuEzaNoArticles'] = get_option( 'bbuEzaNoArticles' );
			// Prefill with the default number but don't save
			if ( empty( $this->bbuEzaOptions['bbuEzaNoArticles'] ) )
				$this->bbuEzaOptions['bbuEzaNoArticles'] = BBUEZA_NO_ARTICLES_DEFAULT;
			
			$this->bbuEzaOptions['bbuEzaCatSelected'] = get_option( 'bbuEzaCatSelected' );
			$this->bbuEzaOptions['bbuEzaSubCatSelected'] = get_option( 'bbuEzaSubCatSelected' );
		}
		
		// Read categories from wp_options table and prepare as array
		function bbuEzaReadCategories() {
			$this->bbuEzaAllCategories = unserialize( get_option( 'bbuEzaAllCategories' ) );
		}

		// Fetch newest categories from EzineArticles and update the wp_options table
		function bbuEzaUpdateCategories() {
			$this->bbuEzaReadOptions();
			$bbuEzaRequestCategoryUrl = $this->bbuEzaApiUrl . '?search=categories&response_format=phpserial&key=';
			if ( !empty( $this->bbuEzaOptions['bbuEzaApiKey'] ) )
				$bbuEzaRequestCategoryUrl .= $this->bbuEzaOptions['bbuEzaApiKey'];
			else
				return FALSE;
			
			$response = wp_remote_request( $bbuEzaRequestCategoryUrl );
			
			if ( !is_wp_error( $response ) and ( $response['response']['code'] == 200 ) ) {
				// Save the category listings to database
				update_option( 'bbuEzaAllCategories', $response['body'] );
				return TRUE;
			} else {
				return FALSE;
			}
		}

		// Create query string based on user options, fetch data from EzineArticles.com, and return body or false			
		function bbuEzaQuery() {
			$this->bbuEzaReadOptions();

			$bbuEzaRequest = $this->bbuEzaApiUrl;
			
			if ( !empty( $this->bbuEzaOptions['bbuEzaSearch'] ) )
				$bbuEzaRequest .= "?search={$this->bbuEzaOptions['bbuEzaSearch']}";
			else
				die(); // Quit gracefully, someone tampering with the database?
				
			if ( !empty( $this->bbuEzaOptions['bbuEzaAuthor'] ) )
				$bbuEzaRequest .= '&author=' . urlencode( $this->bbuEzaOptions['bbuEzaAuthor'] );
				
			if ( !empty( $this->bbuEzaOptions['bbuEzaNoArticles'] ) and is_numeric( $this->bbuEzaOptions['bbuEzaNoArticles'] ) and ( $this->bbuEzaOptions['bbuEzaNoArticles'] > 0 ) )
				$bbuEzaRequest .= "&limit={$this->bbuEzaOptions['bbuEzaNoArticles']}";
			else
				$bbuEzaRequest .= '&limit=' . BBUEZA_NO_ARTICLES_DEFAULT;
				
			if ( !empty( $this->bbuEzaOptions['bbuEzaApiKey'] ) )
				$bbuEzaRequest .= "&key={$this->bbuEzaOptions['bbuEzaApiKey']}";
			else
				die();
			
			if ( !empty( $this->bbuEzaOptions['bbuEzaCatSelected'] ) )
				$bbuEzaRequest .= '&category=' . str_replace( ' ', '-', $this->bbuEzaOptions['bbuEzaCatSelected'] );
			
			if ( !empty( $this->bbuEzaOptions['bbuEzaSubCatSelected'] ) )
				$bbuEzaRequest .= '&subcategory=' . str_replace( ' ', '-', $this->bbuEzaOptions['bbuEzaSubCatSelected'] );
			
			$bbuEzaRequest .= '&response_format=phpserial';

			// Only available since WP 2.7
			$response = wp_remote_request( $bbuEzaRequest, array( 'timeout' => 15 ) );
			
			if ( !is_wp_error( $response ) and ( $response['response']['code'] == 200 ) )
				return $response['body'];
			else
				return FALSE;
		}

		// Save the newly fetched data to wp_options table
		function bbuEzaSaveData( $serialized ) {
			update_option( 'bbuEzaData', $serialized );
		}

		// Prompt for "widget title" in the widget control area
		function bbuEzaWidgetControl() {
			if ( 'update' == $_REQUEST['action'] ) {
				if ( !empty( $_REQUEST['bbuEzaWidgetTitle'] ) )
					update_option( 'bbuEzaWidgetTitle', $_REQUEST['bbuEzaWidgetTitle'] );
			}
			
			echo "<p>EZA Title:<br /><input type='text' name='bbuEzaWidgetTitle' value='" . stripslashes( get_option( 'bbuEzaWidgetTitle' ) ) . "' size='30' class='regular-text' /></p>";
			settings_fields( 'bbuEza' );
		}

		// Render the widget main content in the sidebar		
		function bbuEzaRenderWidget() {
			$widgetContent = '';
			$bbuEzaData = stripslashes( get_option( 'bbuEzaData' ) );
			
			if ( !count( $bbuEzaData ) )
				return '';
			
			$bbuEzaData = unserialize( $bbuEzaData );
			
			// Start rendering widget data
			$widgetContent .= "<ul>\n";
			
			$j = count( $this->bbuEzaSearchValues );

			for ( $i = 0; $i < $j; $i++ ) {
				$l = count( $bbuEzaData[$this->bbuEzaSearchValues[$i]] );
				if ( $l ) {
					for ( $k = 0; $k < $l; $k++ ) {
						$widgetContent .= "<li><a href='" . $bbuEzaData[$this->bbuEzaSearchValues[$i]][$k]['article']['url'] . "'>" . $bbuEzaData[$this->bbuEzaSearchValues[$i]][$k]['article']['title'] . "</a></li>";
					}
				}
			}
			
			$widgetContent .= "</ul>\n";
			
			return $widgetContent;
		}

		// Display the widget
		function bbuEzaWidget( $args ) {
			extract( $args, EXTR_SKIP );
			echo $before_widget;
			echo $before_title;
			echo get_option( 'bbuEzaWidgetTitle' );
			echo $after_title;
			echo $this->bbuEzaRenderWidget();
			echo $after_widget;
		}

		// Hook into the WordPress dashboard menu		
		function bbuEzaAdminMenu() {
			add_options_page( 'Eza Search API', 'Eza Search API', manage_options, basename( __FILE__ ), array( &$this, 'bbuEzaSetOptions' ) );
		}

		// Prepare the search options for display as HTML <select> and <options>. Returns as formatted HTML.
		function bbuEzaPrepareSearchHtml() {
			$searchOptions = '<select name="bbuEzaSearch">';
			$j = count( $this->bbuEzaSearchValues );
			for ( $i = 0; $i < $j; $i++ ) {
				$searchOptions .= "<option value='" . $this->bbuEzaSearchValues[$i] . "'";
				if ( $this->bbuEzaOptions['bbuEzaSearch'] == $this->bbuEzaSearchValues[$i] )
					$searchOptions .= ' selected';

				$searchOptions .= '>';
					
				switch ( $this->bbuEzaSearchValues[$i] ) {
					case 'articles':
						$searchOptions .= 'Articles';
						break;
					case 'articles.most.viewed':
						$searchOptions .= 'Most Viewed Articles';
						break;
					case 'articles.most.recent':
						$searchOptions .= 'Most Recent Articles';
						break;
					case 'articles.most.published':
						$searchOptions .= 'Most Published Articles';
						break;
					case 'articles.most.emailed':
						$searchOptions .= 'Most Emailed Articles';
						break;
				}
				
				$searchOptions .= '</option>';
			}
			
			$searchOptions .= '</select>';
			
			return $searchOptions;
		}

		// Prepare the categories and sub-categories in HTML <select> and <options>. Returns as formatted HTML.			
		function bbuEzaPrepareCategoriesHtml( $catArray ) {
			// First option should be blank. This field is optional.
			$catOptions = '<select name="bbuEzaCatDropDown"><option value="">Choose a Category</option>';
			$j = count( $catArray );
			// loops through multi-dimensional array
			for ( $i = 0; $i < $j; $i++ ) {
				$catOptions .= "<option value='" . $catArray[$i]['category']['name'] . "'";
				if ( ( $this->bbuEzaOptions['bbuEzaCatSelected'] == $catArray[$i]['category']['name'] ) and ( empty( $this->bbuEzaOptions['bbuEzaSubCatSelected'] ) ) )
				  $catOptions .= ' selected';
				  
				$catOptions .= ">" . $catArray[$i]['category']['name'] . "</option>";
				
				if ( is_array( $catArray[$i]['category']['subcategory'] ) ) {
					$subCatArray = $catArray[$i]['category']['subcategory'];
				  $l = count( $subCatArray );
				  for ( $k = 0; $k < $l; $k++ ) {
				  	if ( !empty( $subCatArray[$k] ) ) {
				  		$catOptions .= "<option value='" . $subCatArray[$k] . "'";
				  		if ( ( $this->bbuEzaOptions['bbuEzaSubCatSelected'] == $subCatArray[$k] ) )
				  			$catOptions .= ' selected';
				  			
				  		$catOptions .= ">&nbsp;&nbsp;" . $subCatArray[$k] . "</option>";
				  	}
				  }
				}
			}
			
			$catOptions .= '</select>';
			return $catOptions;
		}	

		// Accept category / sub-category name and return an array of category and sub-categery of which the name belongs to
		function bbuEzaGetCatSubCat( $catValue ) {
			$currentParentCat = '';
			$j = count( $this->bbuEzaAllCategories['categories'] );
			
			for ( $i = 0; $i < $j; $i++ ) {
				$currentParentCat = $this->bbuEzaAllCategories['categories'][$i]['category']['name'];
				if ( $catValue == $currentParentCat )
					return array( $currentParentCat, '' );

				if ( is_array( $this->bbuEzaAllCategories['categories'][$i]['category']['subcategory'] ) ) {
					$l = count( $this->bbuEzaAllCategories['categories'][$i]['category']['subcategory'] );
					for ( $k = 0; $k < $l; $k++ ) {
						if ( $catValue == $this->bbuEzaAllCategories['categories'][$i]['category']['subcategory'][$k] )
							return array( $currentParentCat, $this->bbuEzaAllCategories['categories'][$i]['category']['subcategory'][$k] );
					}
				}
			}
		}

		// Saves all the options in wp_options table		
		function bbuEzaSaveOptions() {
			if ( empty( $_REQUEST['bbuEzaApiKey'] ) or empty( $_REQUEST['bbuEzaNoArticles'] ) )
				return FALSE;
			
			update_option( 'bbuEzaApiKey', $_REQUEST['bbuEzaApiKey'] );
			update_option( 'bbuEzaSearch', $_REQUEST['bbuEzaSearch'] );
			update_option( 'bbuEzaAuthor', $_REQUEST['bbuEzaAuthor'] );
			if ( $_REQUEST['bbuEzaNoArticles'] > BBUEZA_MAX_ARTICLES )
				update_option( 'bbuEzaNoArticles', BBUEZA_MAX_ARTICLES );
			else
				update_option( 'bbuEzaNoArticles', $_REQUEST['bbuEzaNoArticles'] );

			if ( empty( $_REQUEST['bbuEzaCatDropDown'] ) ) {
				update_option( 'bbuEzaCatSelected', '' );
				update_option( 'bbuEzaSubCatSelected', '' );
			} else {
				$catSubCat = $this->bbuEzaGetCatSubCat( $_REQUEST['bbuEzaCatDropDown'] );
				update_option( 'bbuEzaCatSelected', $catSubCat[0] );
				update_option( 'bbuEzaSubCatSelected', $catSubCat[1] );
			}
			return TRUE;
		}

		// Displays option page and allows the users to save options			
		function bbuEzaSetOptions() {
			$this->bbuEzaReadCategories();
			if ( ( 'update' == $_REQUEST['action'] ) and ( 'Save Options' == $_REQUEST['submit'] ) ) {
				$saveStatus = $this->bbuEzaSaveOptions();
				if ( !$saveStatus ) 
					echo "Please enter the required data.";
				
				$bbuEzaQueryData = $this->bbuEzaQuery();
				if ( !$bbuEzaQueryData ) {
					echo "Fail to fetch data from EzineArticles API\n\n";
				} else {
					$this->bbuEzaSaveData( $bbuEzaQueryData );
				}
			}
			
			if ( 'Update EZA Categories' == $_REQUEST['updatecategory'] ) {
				$bbuEzaCategoryUpdateStatus = $this->bbuEzaUpdateCategories();
			
				if ( !$bbuEzaCategoryUpdateStatus )
					echo 'Error while updating categories. Most likely no API key or connection error. Make sure you have saved an API key and then try again.';
			}
				
			// Get all options in one swoop. I know this is lazy ;)
			$this->bbuEzaReadOptions();
			
			// Prepare for the search type drop down menu (HTML);
			$this->bbuEzaSearchHtml = $this->bbuEzaPrepareSearchHtml();
			
			// Prepare the categories and sub-categories as drop down menu (HTML)
			$this->bbuEzaAllCategoriesHtml = $this->bbuEzaPrepareCategoriesHtml( $this->bbuEzaAllCategories['categories'] );
			
			echo <<<OPTION_FORM
<div class='wrap'>
<div id='icon-options-general' class='icon32'><br /></div>
<h2>BBU's EzineArticles Search API Options</h2>
<p>Get more Internet marketing related plugins by visiting <a href='http://blogbuildingu.com/'>Blog Building University</a>.</p>
<form action='' method='post'>
	<ol>
		<li>EzineArticles API Key:<br /><input type="text" name="bbuEzaApiKey" value="{$this->bbuEzaOptions['bbuEzaApiKey']}" size="35" class="regular-text" /></li>
		<li>What to Search:<br />$this->bbuEzaSearchHtml</li>
		<li>Author:<br /><input type="text" name="bbuEzaAuthor" value="{$this->bbuEzaOptions['bbuEzaAuthor']}" size="35" class="regular-text" /></li>
		<li>No. of Articles to Display:<br /><input type="text" name="bbuEzaNoArticles" value="{$this->bbuEzaOptions['bbuEzaNoArticles']}" size="5" class="regular-text" /></li>
		<li>Category / Sub-Category:<br />$this->bbuEzaAllCategoriesHtml</li>
	</ol>
OPTION_FORM;
settings_fields( 'bbuEza' );
echo "
<p><input type='submit' name='submit' value='Save Options' class='button-primary' /></p>
</form>
</div>";

		echo <<<UPDATE_CATEGORY_FORM
<h3>Update EzineArticles Categories</h3>
<p>EzineArticles updates category listings regularly. If you want to upgrade your data, press the button below.</p>
<form action='' method='post'>
<p><input type='submit' name='updatecategory' value='Update EZA Categories' class='button-secondary' /></p>
</form>
UPDATE_CATEGORY_FORM;
		}

		// Initiate sidebar widget and widget control				
		function bbuEzaWidgetInit() {
			// Check to see whether Widget API functions are defined
			if ( !function_exists( 'register_sidebar_widget' ) || !function_exists( 'register_widget_control' ) )
				return; // if not, exit gracefully from the plugin
			
			// Register the widget
			register_sidebar_widget( 'Eza Search', array( &$this, 'bbuEzaWidget' ) );
			
			// Register the widget control form
			register_widget_control( 'Eza Search', array( &$this, 'bbuEzaWidgetControl' ) );
		}

		// Schedule for automatic data update every one hour using WordPress internal scheduling (wp cron)
		function bbuEzaSchedule() {
			wp_schedule_event( time() + 3600, 'hourly', 'bbuEzaUpdateData' );
		}
		
		// This function hooks into the WordPress new schedule event
		function bbuEzaFetchData() {
			$bbuEzaQueryData = $this->bbuEzaQuery();
			if ( !empty( $bbuEzaQueryData ) )
				$this->bbuEzaSaveData( $bbuEzaQueryData );
		}

		// When user deactivates this plugin, clear the scheduled hook
		function bbuEzaDeactivate() {
			wp_clear_scheduled_hook( 'bbuEzaUpdateData' );
		}

		// When user delete the plugin, unregister and delete all options in wp_options table
		function bbuEzaUninstall() {
			unregister_setting( 'bbuEza', 'bbuEzaApiKey' );
			unregister_setting( 'bbuEza', 'bbuEzaSearch' );
			unregister_setting( 'bbuEza', 'bbuEzaAuthor' );
			unregister_setting( 'bbuEza', 'bbuEzaNoArticles' );
			unregister_setting( 'bbuEza', 'bbuEzaCatSelected' );
			unregister_setting( 'bbuEza', 'bbuEzaSubCatSelected' );
			unregister_setting( 'bbuEza', 'bbuEzaWidgetTitle' );
			unregister_setting( 'bbuEza', 'bbuEzaData' );
			unregister_setting( 'bbuEza', 'bbuEzaAllCategories' );
			delete_option( 'bbuEzaApiKey' );
			delete_option( 'bbuEzaSearch' );
			delete_option( 'bbuEzaAuthor' );
			delete_option( 'bbuEzaNoArticles' );
			delete_option( 'bbuEzaCatSelected' );
			delete_option( 'bbuEzaSubCatSelected' );
			delete_option( 'bbuEzaWidgetTitle' );
			delete_option( 'bbuEzaData' );
			delete_option( 'bbuEzaAllCategories' );
		}

		// Initialize the plugin by register used options
		// Load the initial EzineArticles categories into wp_options
		function bbuEzaInit() {
			register_setting( 'bbuEza', 'bbuEzaApiKey' );
			register_setting( 'bbuEza', 'bbuEzaSearch' );
			register_setting( 'bbuEza', 'bbuEzaAuthor' );
			register_setting( 'bbuEza', 'bbuEzaNoArticles' );
			register_setting( 'bbuEza', 'bbuEzaCatSelected' );
			register_setting( 'bbuEza', 'bbuEzaSubCatSelected' );
			register_setting( 'bbuEza', 'bbuEzaWidgetTitle' );
			register_setting( 'bbuEza', 'bbuEzaData' );
			
			// Import initial categories and sub-categories
			$bbuEzaCatTemp = <<<EOD
a:2:{s:10:"categories";a:30:{i:0;a:1:{s:8:"category";a:2:{s:4:"name";s:22:"Arts and Entertainment";s:11:"subcategory";a:13:{i:0;s:9:"Animation";i:1;s:9:"Astrology";i:2;s:15:"Casino Gambling";i:3;s:10:"Humanities";i:4;s:5:"Humor";i:5;s:9:"Movies TV";i:6;s:5:"Music";i:7;s:15:"Performing Arts";i:8;s:10:"Philosophy";i:9;s:11:"Photography";i:10;s:6:"Poetry";i:11;s:7:"Tattoos";i:12;s:19:"Visual Graphic Arts";}}}i:1;a:1:{s:8:"category";a:2:{s:4:"name";s:10:"Automotive";s:11:"subcategory";a:6:{i:0;s:3:"ATV";i:1;s:18:"Mobile Audio Video";i:2;s:11:"Motorcycles";i:3;s:2:"RV";i:4;s:7:"Repairs";i:5;s:6:"Trucks";}}}i:2;a:1:{s:8:"category";a:2:{s:4:"name";s:12:"Book Reviews";s:11:"subcategory";a:49:{i:0;s:8:"Almanacs";i:1;s:16:"Arts Photography";i:2;s:19:"Biographies Memoirs";i:3;s:7:"Biology";i:4;s:8:"Business";i:5;s:15:"Childrens Books";i:6;s:12:"Comics Humor";i:7;s:9:"Computers";i:8;s:16:"Cookery Cookbook";i:9;s:15:"Current Affairs";i:10;s:5:"Dance";i:11;s:9:"Economics";i:12;s:19:"Educational Science";i:13;s:7:"Fiction";i:14;s:16:"Health Mind Body";i:15;s:7:"History";i:16;s:11:"Home Garden";i:17;s:21:"Inspirational Fiction";i:18;s:8:"Internet";i:19;s:18:"Internet Marketing";i:20;s:9:"Law Legal";i:21;s:9:"Lifestyle";i:22;s:17:"Literary Classics";i:23;s:11:"Maps Guides";i:24;s:4:"Mens";i:25;s:13:"Multicultural";i:26;s:5:"Music";i:27;s:19:"Mysteries Thrillers";i:28;s:11:"Non Fiction";i:29;s:16:"Personal Finance";i:30;s:4:"Pets";i:31;s:10:"Philosophy";i:32;s:7:"Physics";i:33;s:18:"Poetry Playscripts";i:34;s:8:"Politics";i:35;s:10:"Psychology";i:36;s:33:"Reference Encyclopedia Dictionary";i:37;s:7:"Romance";i:38;s:20:"SciFi Fantasy Horror";i:39;s:9:"Self Help";i:40;s:13:"Short Stories";i:41;s:21:"Spirituality Religion";i:42;s:17:"Sports Literature";i:43;s:14:"Travel Leisure";i:44;s:15:"Wealth Building";i:45;s:16:"Weight Loss Diet";i:46;s:8:"Westerns";i:47;s:6:"Womens";i:48;s:12:"Young Adults";}}}i:3;a:1:{s:8:"category";a:2:{s:4:"name";s:8:"Business";s:11:"subcategory";a:43:{i:0;s:10:"Accounting";i:1;s:18:"Accounting Payroll";i:2;s:11:"Advertising";i:3;s:8:"Branding";i:4;s:13:"Career Advice";i:5;s:18:"Careers Employment";i:6;s:17:"Change Management";i:7;s:10:"Consulting";i:8;s:28:"Continuity Disaster Recovery";i:9;s:16:"Customer Service";i:10;s:18:"Entrepreneurialism";i:11;s:6:"Ethics";i:12;s:11:"Franchising";i:13;s:11:"Fundraising";i:14;s:15:"Human Resources";i:15;s:21:"Industrial Mechanical";i:16;s:22:"International Business";i:17;s:21:"Job Search Techniques";i:18;s:10:"Management";i:19;s:9:"Marketing";i:20;s:16:"Marketing Direct";i:21;s:11:"Negotiation";i:22;s:10:"Networking";i:23;s:10:"Non Profit";i:24;s:11:"Outsourcing";i:25;s:2:"PR";i:26;s:12:"Presentation";i:27;s:12:"Productivity";i:28;s:21:"Resumes Cover Letters";i:29;s:6:"Retail";i:30;s:5:"Sales";i:31;s:16:"Sales Management";i:32;s:17:"Sales Teleselling";i:33;s:14:"Sales Training";i:34;s:8:"Security";i:35;s:14:"Small Business";i:36;s:18:"Solo Professionals";i:37;s:18:"Strategic Planning";i:38;s:13:"Team Building";i:39;s:15:"Top7 or 10 Tips";i:40;s:15:"Venture Capital";i:41;s:23:"Workplace Communication";i:42;s:16:"Workplace Safety";}}}i:4;a:1:{s:8:"category";a:2:{s:4:"name";s:6:"Cancer";s:11:"subcategory";a:7:{i:0;s:13:"Breast Cancer";i:1;s:19:"Colon Rectal Cancer";i:2;s:24:"Leukemia Lymphoma Cancer";i:3;s:26:"Lung Mesothelioma Asbestos";i:4;s:31:"Ovarian Cervical Uterine Cancer";i:5;s:15:"Prostate Cancer";i:6;s:11:"Skin Cancer";}}}i:5;a:1:{s:8:"category";a:2:{s:4:"name";s:14:"Communications";s:11:"subcategory";a:13:{i:0;s:18:"Broadband Internet";i:1;s:3:"Fax";i:2;s:3:"GPS";i:3;s:17:"Mobile Cell Phone";i:4;s:29:"Mobile Cell Phone Accessories";i:5;s:25:"Mobile Cell Phone Reviews";i:6;s:21:"Mobile Cell Phone SMS";i:7;s:5:"Radio";i:8;s:15:"Satellite Radio";i:9;s:12:"Satellite TV";i:10;s:17:"Telephone Systems";i:11;s:4:"VOIP";i:12;s:18:"Video Conferencing";}}}i:6;a:1:{s:8:"category";a:2:{s:4:"name";s:24:"Computers and Technology";s:11:"subcategory";a:7:{i:0;s:19:"Certification Tests";i:1;s:18:"Computer Forensics";i:2;s:13:"Data Recovery";i:3;s:8:"Hardware";i:4;s:16:"Mobile Computing";i:5;s:13:"Personal Tech";i:6;s:8:"Software";}}}i:7;a:1:{s:8:"category";a:2:{s:4:"name";s:7:"Finance";s:11:"subcategory";a:32:{i:0;s:10:"Auto Loans";i:1;s:10:"Bankruptcy";i:2;s:18:"Bankruptcy Lawyers";i:3;s:18:"Bankruptcy Medical";i:4;s:19:"Bankruptcy Personal";i:5;s:22:"Bankruptcy Tips Advice";i:6;s:9:"Budgeting";i:7;s:16:"Commercial Loans";i:8;s:6:"Credit";i:9;s:17:"Credit Counseling";i:10;s:11:"Credit Tips";i:11;s:16:"Currency Trading";i:12;s:18:"Debt Consolidation";i:13;s:15:"Debt Management";i:14;s:11:"Debt Relief";i:15;s:18:"Estate Plan Trusts";i:16;s:17:"Home Equity Loans";i:17;s:14:"Leases Leasing";i:18;s:5:"Loans";i:19;s:12:"PayDay Loans";i:20;s:16:"Personal Finance";i:21;s:14:"Personal Loans";i:22;s:22:"Structured Settlements";i:23;s:13:"Student Loans";i:24;s:5:"Taxes";i:25;s:12:"Taxes Income";i:26;s:14:"Taxes Property";i:27;s:12:"Taxes Relief";i:28;s:11:"Taxes Tools";i:29;s:15:"Unsecured Loans";i:30;s:8:"VA Loans";i:31;s:15:"Wealth Building";}}}i:8;a:1:{s:8:"category";a:2:{s:4:"name";s:14:"Food and Drink";s:11:"subcategory";a:14:{i:0;s:9:"Chocolate";i:1;s:6:"Coffee";i:2;s:12:"Cooking Tips";i:3;s:16:"Crockpot Recipes";i:4;s:8:"Desserts";i:5;s:11:"Low Calorie";i:6;s:11:"Main Course";i:7;s:12:"Pasta Dishes";i:8;s:7:"Recipes";i:9;s:18:"Restaurant Reviews";i:10;s:6:"Salads";i:11;s:5:"Soups";i:12;s:3:"Tea";i:13;s:12:"Wine Spirits";}}}i:9;a:1:{s:8:"category";a:2:{s:4:"name";s:6:"Gaming";s:11:"subcategory";a:6:{i:0;s:11:"Communities";i:1;s:14:"Computer Games";i:2;s:13:"Console Games";i:3;s:15:"Console Systems";i:4;s:13:"Online Gaming";i:5;s:18:"Video Game Reviews";}}}i:10;a:1:{s:8:"category";a:2:{s:4:"name";s:18:"Health and Fitness";s:11:"subcategory";a:63:{i:0;s:4:"Acne";i:1;s:15:"Aerobics Cardio";i:2;s:9:"Allergies";i:3;s:11:"Alternative";i:4;s:10:"Anti Aging";i:5;s:7:"Anxiety";i:6;s:9:"Arthritis";i:7;s:6:"Asthma";i:8;s:6:"Autism";i:9;s:9:"Back Pain";i:10;s:6:"Beauty";i:11;s:12:"Build Muscle";i:12;s:28:"Childhood Obesity Prevention";i:13;s:28:"Contraceptives Birth Control";i:14;s:13:"Critical Care";i:15;s:11:"Dental Care";i:16;s:10:"Depression";i:17;s:14:"Detoxification";i:18;s:26:"Developmental Disabilities";i:19;s:8:"Diabetes";i:20;s:10:"Disability";i:21;s:8:"Diseases";i:22;s:27:"Diseases Multiple Sclerosis";i:23;s:13:"Diseases STDs";i:24;s:10:"Drug Abuse";i:25;s:12:"Ears Hearing";i:26;s:16:"Eating Disorders";i:27;s:27:"Emotional Freedom Technique";i:28;s:20:"Environmental Issues";i:29;s:10:"Ergonomics";i:30;s:8:"Exercise";i:31;s:11:"Eyes Vision";i:32;s:17:"Fitness Equipment";i:33;s:9:"Hair Loss";i:34;s:15:"Hand Wrist Pain";i:35;s:19:"Headaches Migraines";i:36;s:12:"Healing Arts";i:37;s:18:"Healthcare Systems";i:38;s:13:"Heart Disease";i:39;s:16:"Home Health Care";i:40;s:12:"Hypertension";i:41;s:7:"Massage";i:42;s:8:"Medicine";i:43;s:10:"Meditation";i:44;s:11:"Mens Issues";i:45;s:13:"Mental Health";i:46;s:16:"Mind Body Spirit";i:47;s:14:"Mood Disorders";i:48;s:9:"Nutrition";i:49;s:7:"Obesity";i:50;s:15:"Pain Management";i:51;s:16:"Physical Therapy";i:52;s:13:"Popular Diets";i:53;s:12:"Quit Smoking";i:54;s:13:"Self Hypnosis";i:55;s:9:"Skin Care";i:56;s:13:"Sleep Snoring";i:57;s:16:"Speech Pathology";i:58;s:11:"Supplements";i:59;s:7:"Thyroid";i:60;s:11:"Weight Loss";i:61;s:13:"Womens Issues";i:62;s:4:"Yoga";}}}i:11;a:1:{s:8:"category";a:2:{s:4:"name";s:15:"Home and Family";s:11:"subcategory";a:17:{i:0;s:14:"Babies Toddler";i:1;s:11:"Baby Boomer";i:2;s:14:"Crafts Hobbies";i:3;s:15:"Crafts Supplies";i:4;s:11:"Death Dying";i:5;s:10:"Elder Care";i:6;s:12:"Entertaining";i:7;s:10:"Fatherhood";i:8;s:9:"Gardening";i:9;s:14:"Grandparenting";i:10;s:8:"Holidays";i:11;s:10:"Motherhood";i:12;s:9:"Parenting";i:13;s:7:"Parties";i:14;s:9:"Pregnancy";i:15;s:10:"Retirement";i:16;s:12:"Scrapbooking";}}}i:12;a:1:{s:8:"category";a:2:{s:4:"name";s:19:"Home Based Business";s:11:"subcategory";a:1:{i:0;s:17:"Network Marketing";}}}i:13;a:1:{s:8:"category";a:2:{s:4:"name";s:16:"Home Improvement";s:11:"subcategory";a:34:{i:0;s:10:"Appliances";i:1;s:11:"Audio Video";i:2;s:15:"Bath and Shower";i:3;s:8:"Cabinets";i:4;s:23:"Cleaning Tips and Tools";i:5;s:8:"Concrete";i:6;s:3:"DIY";i:7;s:5:"Doors";i:8;s:10:"Electrical";i:9;s:17:"Energy Efficiency";i:10;s:9:"Feng Shui";i:11;s:8:"Flooring";i:12;s:10:"Foundation";i:13;s:9:"Furniture";i:14;s:28:"Heating and Air Conditioning";i:15;s:11:"House Plans";i:16;s:30:"Interior Design and Decorating";i:17;s:20:"Kitchen Improvements";i:18;s:30:"Landscaping Outdoor Decorating";i:19;s:8:"Lighting";i:20;s:16:"New Construction";i:21;s:8:"Painting";i:22;s:10:"Patio Deck";i:23;s:12:"Pest Control";i:24;s:8:"Plumbing";i:25;s:10:"Remodeling";i:26;s:7:"Roofing";i:27;s:8:"Security";i:28;s:11:"Stone Brick";i:29;s:14:"Storage Garage";i:30;s:19:"Swimming Pools Spas";i:31;s:19:"Tools and Equipment";i:32;s:7:"Windows";i:33;s:14:"Yard Equipment";}}}i:14;a:1:{s:8:"category";a:2:{s:4:"name";s:9:"Insurance";s:11:"subcategory";a:20:{i:0;s:16:"Agents Marketers";i:1;s:8:"Car Auto";i:2;s:10:"Commercial";i:3;s:6:"Dental";i:4;s:10:"Disability";i:5;s:5:"Flood";i:6;s:6:"Health";i:7;s:19:"Home Owners Renters";i:8;s:14:"Life Annuities";i:9;s:14:"Long Term Care";i:10;s:15:"Medical Billing";i:11;s:17:"Personal Property";i:12;s:3:"Pet";i:13;s:13:"RV Motorcycle";i:14;s:12:"Supplemental";i:15;s:6:"Travel";i:16;s:8:"Umbrella";i:17;s:6:"Vision";i:18;s:10:"Watercraft";i:19;s:20:"Workers Compensation";}}}i:15;a:1:{s:8:"category";a:2:{s:4:"name";s:30:"Internet and Businesses Online";s:11:"subcategory";a:37:{i:0;s:17:"Affiliate Revenue";i:1;s:8:"Auctions";i:2;s:15:"Audio Streaming";i:3;s:14:"Autoresponders";i:4;s:18:"Banner Advertising";i:5;s:8:"Blogging";i:6;s:3:"CMS";i:7;s:12:"Domain Names";i:8;s:7:"E Books";i:9;s:9:"Ecommerce";i:10;s:15:"Email Marketing";i:11;s:16:"Ezine Publishing";i:12;s:6:"Forums";i:13;s:18:"Internet Marketing";i:14;s:15:"Link Popularity";i:15;s:13:"List Building";i:16;s:15:"PPC Advertising";i:17;s:14:"PPC Publishing";i:18;s:12:"Paid Surveys";i:19;s:10:"Podcasting";i:20;s:16:"Product Creation";i:21;s:17:"Product Launching";i:22;s:3:"RSS";i:23;s:3:"SEO";i:24;s:23:"Search Engine Marketing";i:25;s:8:"Security";i:26;s:14:"Site Promotion";i:27;s:18:"Social Bookmarking";i:28;s:12:"Social Media";i:29;s:17:"Social Networking";i:30;s:12:"Spam Blocker";i:31;s:16:"Traffic Building";i:32;s:15:"Video Marketing";i:33;s:15:"Video Streaming";i:34;s:10:"Web Design";i:35;s:15:"Web Development";i:36;s:11:"Web Hosting";}}}i:16;a:1:{s:8:"category";a:2:{s:4:"name";s:9:"Investing";s:11:"subcategory";a:6:{i:0;s:11:"Day Trading";i:1;s:23:"Futures and Commodities";i:2;s:8:"IRA 401k";i:3;s:12:"Mutual Funds";i:4;s:19:"Retirement Planning";i:5;s:6:"Stocks";}}}i:17;a:1:{s:8:"category";a:2:{s:4:"name";s:14:"Kids and Teens";s:11:"subcategory";a:1:{i:0;s:0:"";}}}i:18;a:1:{s:8:"category";a:2:{s:4:"name";s:5:"Legal";s:11:"subcategory";a:18:{i:0;s:9:"Copyright";i:1;s:16:"Corporations LLC";i:2;s:12:"Criminal Law";i:3;s:9:"Cyber Law";i:4;s:9:"Elder Law";i:5;s:14:"Employment Law";i:6;s:14:"Identity Theft";i:7;s:11:"Immigration";i:8;s:21:"Intellectual Property";i:9;s:9:"Labor Law";i:10;s:11:"Living Will";i:11;s:19:"Medical Malpractice";i:12;s:20:"National State Local";i:13;s:7:"Patents";i:14;s:15:"Personal Injury";i:15;s:15:"Real Estate Law";i:16;s:21:"Regulatory Compliance";i:17;s:10:"Trademarks";}}}i:19;a:1:{s:8:"category";a:2:{s:4:"name";s:16:"News and Society";s:11:"subcategory";a:10:{i:0;s:5:"Crime";i:1;s:9:"Economics";i:2;s:6:"Energy";i:3;s:13:"Environmental";i:4;s:13:"International";i:5;s:8:"Military";i:6;s:8:"Politics";i:7;s:12:"Pure Opinion";i:8;s:8:"Religion";i:9;s:7:"Weather";}}}i:20;a:1:{s:8:"category";a:2:{s:4:"name";s:4:"Pets";s:11:"subcategory";a:8:{i:0;s:5:"Birds";i:1;s:4:"Cats";i:2;s:4:"Dogs";i:3;s:6:"Exotic";i:4;s:10:"Farm Ranch";i:5;s:4:"Fish";i:6;s:6:"Horses";i:7;s:19:"Reptiles Amphibians";}}}i:21;a:1:{s:8:"category";a:2:{s:4:"name";s:11:"Real Estate";s:11:"subcategory";a:18:{i:0;s:15:"Building a Home";i:1;s:6:"Buying";i:2;s:23:"Commercial Construction";i:3;s:19:"Commercial Property";i:4;s:12:"Condominiums";i:5;s:4:"FSBO";i:6;s:12:"Foreclosures";i:7;s:17:"Green Real Estate";i:8;s:12:"Home Staging";i:9;s:5:"Homes";i:10;s:9:"Investing";i:11;s:4:"Land";i:12;s:15:"Leasing Renting";i:13;s:9:"Marketing";i:14;s:18:"Mortgage Refinance";i:15;s:17:"Moving Relocating";i:16;s:19:"Property Management";i:17;s:7:"Selling";}}}i:22;a:1:{s:8:"category";a:2:{s:4:"name";s:21:"Recreation and Sports";s:11:"subcategory";a:46:{i:0;s:7:"Archery";i:1;s:11:"Auto Racing";i:2;s:8:"Baseball";i:3;s:10:"Basketball";i:4;s:9:"Billiards";i:5;s:7:"Boating";i:6;s:12:"Bodybuilding";i:7;s:7:"Bowling";i:8;s:6:"Boxing";i:9;s:12:"Cheerleading";i:10;s:8:"Climbing";i:11;s:7:"Cricket";i:12;s:7:"Cycling";i:13;s:7:"Dancing";i:14;s:10:"Equestrian";i:15;s:7:"Extreme";i:16;s:14:"Fantasy Sports";i:17;s:14:"Figure Skating";i:18;s:10:"Fish Ponds";i:19;s:7:"Fishing";i:20;s:8:"Football";i:21;s:4:"Golf";i:22;s:10:"Gymnastics";i:23;s:6:"Hockey";i:24;s:12:"Horse Racing";i:25;s:7:"Hunting";i:26;s:12:"Martial Arts";i:27;s:15:"Mountain Biking";i:28;s:8:"Olympics";i:29;s:11:"Racquetball";i:30;s:5:"Rodeo";i:31;s:5:"Rugby";i:32;s:7:"Running";i:33;s:12:"Scuba Diving";i:34;s:13:"Skateboarding";i:35;s:6:"Skiing";i:36;s:12:"Snowboarding";i:37;s:6:"Soccer";i:38;s:14:"Sports Apparel";i:39;s:7:"Surfing";i:40;s:8:"Swimming";i:41;s:6:"Tennis";i:42;s:15:"Track and Field";i:43;s:9:"Triathlon";i:44;s:10:"Volleyball";i:45;s:9:"Wrestling";}}}i:23;a:1:{s:8:"category";a:2:{s:4:"name";s:23:"Reference and Education";s:11:"subcategory";a:15:{i:0;s:9:"Astronomy";i:1;s:18:"College University";i:2;s:13:"Financial Aid";i:3;s:15:"Future Concepts";i:4;s:14:"Home Schooling";i:5;s:9:"Languages";i:6;s:6:"Nature";i:7;s:16:"Online Education";i:8;s:10:"Paranormal";i:9;s:7:"Psychic";i:10;s:10:"Psychology";i:11;s:7:"Science";i:12;s:22:"Survival and Emergency";i:13;s:24:"Vocational Trade Schools";i:14;s:8:"Wildlife";}}}i:24;a:1:{s:8:"category";a:2:{s:4:"name";s:13:"Relationships";s:11:"subcategory";a:19:{i:0;s:7:"Affairs";i:1;s:13:"Anniversaries";i:2;s:10:"Commitment";i:3;s:13:"Communication";i:4;s:8:"Conflict";i:5;s:6:"Dating";i:6;s:18:"Dating for Boomers";i:7;s:7:"Divorce";i:8;s:17:"Domestic Violence";i:9;s:11:"Enhancement";i:10;s:10:"Friendship";i:11;s:11:"Gay Lesbian";i:12;s:4:"Love";i:13;s:8:"Marriage";i:14;s:12:"Post Divorce";i:15;s:9:"Readiness";i:16;s:9:"Sexuality";i:17;s:7:"Singles";i:18;s:7:"Wedding";}}}i:25;a:1:{s:8:"category";a:2:{s:4:"name";s:16:"Self Improvement";s:11:"subcategory";a:29:{i:0;s:20:"Abundance Prosperity";i:1;s:11:"Achievement";i:2;s:10:"Addictions";i:3;s:12:"Affirmations";i:4;s:16:"Anger Management";i:5;s:10:"Attraction";i:6;s:8:"Coaching";i:7;s:10:"Creativity";i:8;s:11:"Empowerment";i:9;s:12:"Goal Setting";i:10;s:10:"Grief Loss";i:11;s:9:"Happiness";i:12;s:10:"Innovation";i:13;s:13:"Inspirational";i:14;s:10:"Leadership";i:15;s:15:"Memory Training";i:16;s:16:"Mind Development";i:17;s:10:"Motivation";i:18;s:12:"NLP Hypnosis";i:19;s:10:"Organizing";i:20;s:15:"Personal Growth";i:21;s:17:"Positive Attitude";i:22;s:11:"Self Esteem";i:23;s:13:"Speed Reading";i:24;s:12:"Spirituality";i:25;s:17:"Stress Management";i:26;s:7:"Success";i:27;s:10:"Techniques";i:28;s:15:"Time Management";}}}i:26;a:1:{s:8:"category";a:2:{s:4:"name";s:28:"Shopping and Product Reviews";s:11:"subcategory";a:7:{i:0;s:19:"Collectible Jewelry";i:1;s:11:"Electronics";i:2;s:13:"Fashion Style";i:3;s:5:"Gifts";i:4;s:18:"Internet Marketing";i:5;s:16:"Jewelry Diamonds";i:6;s:8:"Lingerie";}}}i:27;a:1:{s:8:"category";a:2:{s:4:"name";s:18:"Travel and Leisure";s:11:"subcategory";a:24:{i:0;s:16:"Adventure Travel";i:1;s:14:"Airline Travel";i:2;s:18:"Aviation Airplanes";i:3;s:18:"Bed Breakfast Inns";i:4;s:13:"Budget Travel";i:5;s:7:"Camping";i:6;s:11:"Car Rentals";i:7;s:12:"Charter Jets";i:8;s:27:"City Guides and Information";i:9;s:19:"Cruise Ship Reviews";i:10;s:8:"Cruising";i:11;s:16:"Destination Tips";i:12;s:19:"First Time Cruising";i:13;s:23:"Golf Travel and Resorts";i:14;s:21:"Hotels Accommodations";i:15;s:23:"Limo Rentals Limousines";i:16;s:15:"Luxury Cruising";i:17;s:8:"Outdoors";i:18;s:20:"Pet Friendly Rentals";i:19;s:7:"Sailing";i:20;s:11:"Ski Resorts";i:21;s:9:"Timeshare";i:22;s:14:"Vacation Homes";i:23;s:16:"Vacation Rentals";}}}i:28;a:1:{s:8:"category";a:2:{s:4:"name";s:16:"Womens Interests";s:11:"subcategory";a:3:{i:0;s:16:"Cosmetic Surgery";i:1;s:13:"Menopause HRT";i:2;s:9:"Plus Size";}}}i:29;a:1:{s:8:"category";a:2:{s:4:"name";s:20:"Writing and Speaking";s:11:"subcategory";a:9:{i:0;s:17:"Article Marketing";i:1;s:14:"Book Marketing";i:2;s:11:"Copywriting";i:3;s:15:"Public Speaking";i:4;s:10:"Publishing";i:5;s:17:"Technical Writing";i:6;s:12:"Teleseminars";i:7;s:7:"Writing";i:8;s:16:"Writing Articles";}}}}s:13:"response_info";a:7:{s:4:"time";s:23:"2009-02-24 09:55:47 CST";s:6:"status";i:200;s:6:"search";s:10:"categories";s:6:"format";s:9:"phpserial";s:14:"request_source";s:13:"123.123.23.23";s:20:"hourly_request_limit";s:3:"100";s:25:"remaining_hourly_requests";i:96;}}
EOD;

			register_setting( 'bbuEza', 'bbuEzaAllCategories' );
			update_option( 'bbuEzaAllCategories', $bbuEzaCatTemp );
		}

	} // class BBU_EzaSearchApi

} // if !class_exists

?>