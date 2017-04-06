<?php
/* REQUIRES PHP 7.x AT LEAST */
namespace MarkNotes;

defined('_MARKNOTES') or die('No direct access allowed');

class Settings
{
    protected static $_instance = null;

    private $_folderDocs = '';     // subfolder f.i. 'docs' where markdown files are stored (f.i. "Docs\")
    private $_folderAppRoot = '';
    private $_folderWebRoot = '';

    private $_json = array();

    public function __construct(string $folder = '', array $params = null)
    {
        $this->setFolderAppRoot(dirname(dirname(__FILE__)));
        if ($folder !== '') {
            $this->setFolderWebRoot($folder);
        }

        $this->setFolderDocs(DOC_FOLDER);

        self::readSettings($params);
        return true;
    } // function __construct()

    public static function getInstance(string $folder = '', array $params = null)
    {
        if (self::$_instance === null) {
            self::$_instance = new Settings($folder, $params);
        }

        return self::$_instance;
    } // function getInstance()

    private function loadJSON(array $params = null) : array
    {
        // Read settings.json in this order :
        //   1. the settings.json.dist file to initialize all parameters
        //   2. If present, the settings.json file i.e. the user settings for the application
        //   3. If present, the settings.json file that can be found in the note folder
        //       (so if the note /docs/marknotes/userguide.md if displayed, check if a file
        //       /docs/marknotes/settings.json exists and if so, use it),
        //   4. Finally, a very specific note.json file : if the note /docs/marknotes/userguide.md if displayed,
        //      check if the file /docs/marknotes/userguide.json exists and if so, use it.
        //
        // In this order so the file loaded in step 4 will have the priority and can overwrite global settings

        $aeJSON = \MarkNotes\JSON::getInstance();
        $aeFiles = \MarkNotes\Files::getInstance();

        $json = array();

        // 1. Get the settings.json.dist global file, shipped with each releases of MarkNotes
        if ($aeFiles->fileExists($fname = $this->getFolderWebRoot().'settings.json.dist')) {
            $json = $aeJSON->json_decode($fname, true);
        } else {
            if ($aeFiles->fileExists($fname = $this->getFolderAppRoot().'settings.json.dist')) {
                $json = $aeJSON->json_decode($fname, true);
            }
        }

            // 2. Get the settings.json user's file
        if ($aeFiles->fileExists($fname = $this->getFolderWebRoot().'settings.json')) {
            echo '<h1>'.$fname.'</h1>';
            $arr = $aeJSON->json_decode($fname, true);
            if (count($arr) > 0) {
                $json = array_replace_recursive($json, $aeJSON->json_decode($fname, true));
            }
        }

            // 3. Get the settings.json file that is, perhaps, present in the folder of the note
        if (isset($params['filename'])) {
            $noteFolder = $this->getFolderWebRoot().str_replace('/', DS, dirname($params['filename']));
            $noteJSON = $noteFolder.DS.'settings.json';
            if ($aeFiles->fileExists($noteJSON)) {
                $json = array_replace_recursive($json, $aeJSON->json_decode($noteJSON, true));
            }
        }

            // 4. Get the note_name.json file that is, perhaps, present in the folder of the note.  note_name is the note filename
            // with the .json extension of .md
        if (isset($params['filename'])) {
            $noteFolder = $this->getFolderWebRoot().str_replace('/', DS, $params['filename']);
            $noteJSON = $aeFiles->replaceExtension($noteFolder, 'json');

            if ($aeFiles->fileExists($noteJSON)) {
                $json = array_replace_recursive($json, $aeJSON->json_decode($noteJSON, true));
            }
        }
        return $json;
    }

   /**
    * Read the user's settings i.e. the file "settings.json"
    * Initialize class properties
    * @return {bool [description]
    */
    private function readSettings(array $params = null) : bool
    {
        $aeFiles = \MarkNotes\Files::getInstance();

        $this->_json = $this->loadJSON($params);

        $this->setLanguage(DEFAULT_LANGUAGE);

        if (isset($this->_json['language'])) {
            $this->setLanguage($this->_json['language']);
        }

        /*<!-- build:debug -->*/
        if (isset($this->_json['debug'])) {
            $this->setDebugMode($this->_json['debug'] == 1?true:false); // Debug mode enabled or not
        }
        if (isset($this->_json['development'])) {
            $this->setDevMode($this->_json['development'] == 1?true:false); // Developer mode enabled or not
        }
        /*<!-- endbuild -->*/

        /*

        if (!in_array($this->getFolderDocs(), $this->settingsFoldersAutoOpen)) {
            array_push($this->settingsFoldersAutoOpen, $this->getFolderDocs());
        }

        asort($this->settingsFoldersAutoOpen);

        // Retrieve the password if mentionned
        if (isset($this->_json['password'])) {
            $this->settingsPassword=$this->_json['password'];
        }
        */

        return true;
    }

    /**
    * Return the translation of a given text
     *
    * @param string $variable
    */
    public function getText(string $variable, string $default = '', bool $jsProtect = false) : string
    {
        $lang = &$this->_json['languages'][$this->getLanguage()];

        $return = isset($lang[$variable]) ? $lang[$variable] : '';

        if ($return == '') {
            $lang = &$this->_json['languages'][DEFAULT_LANGUAGE];
            $return = isset($lang[$variable]) ? $lang[$variable] : '';
        }

        if ($return == '') {
            $return = (trim($default) !== '' ? constant($default) : '');
        }

        if ($jsProtect) {
            $return = str_replace("'", "\'", html_entity_decode($return));
        }

        // In case of null (i.e. the translation wasn't found, return at least the name of the variable)
        return ($return === null?$variable:$return);
    }

    /**
     * Small sanitization function to be sure that the user willn't type anything in the settings.json file
     * for filename properties
     *
     * @param  string $fname
     * @return string
     */
    private function sanitizeFileName(string $fname) : string
    {
        $fname = trim($fname);

        if ($fname !== '') {
            // should only contains letters, figures or dot/minus/underscore
            if (!preg_match('/^[A-Za-z0-9-_\.]+$/', $fname)) {
                $fname = '';
            }
        } // if ($fname!=='')

        return $fname;
    } // function sanitizeFileName()

    /**
    * Open the package.json file and retrieve an information like the version number from there
    *
    * @return string
    */
    private function getPackageInfo(string $info = 'version') : string
    {
        $aeFiles = \MarkNotes\Files::getInstance();
        $aeJSON = \MarkNotes\JSON::getInstance();
        if ($aeFiles->fileExists($fname = $this->getFolderAppRoot().'package.json')) {
            $json = $aeJSON->json_decode($fname, true);
            return $json[$info];
        } else {
            return '';
        }
    } // function getPackageInfo()

    /**
     * Return the name of the program, with or without its version number (from package.json)
     *
     * @param  bool $addVersionNumber
     * @return string
     */
    public function getAppName(bool $addVersionNumber = false) : string
    {
        $name = APP_NAME;
        if ($addVersionNumber) {
            $name .= ' v.'.self::getPackageInfo('version');
        }
        return $name;
    } // function getAppName()

    /**
     * Return the homepage of the program (from package.json)
     *
     * @return string
     */
    public function getAppHomepage() : string
    {
        return self::getPackageInfo('homepage');
    } // function getAppHomepage()

    public function getLanguage() : string
    {
        return $this->language;
    }

    public function setLanguage(string $lang)
    {
        if (isset($this->_json['languages'][$lang])) {
            // The language should exists in the settings.json languages node
            // If not, use the default one
            $this->language = $lang;
        } else {
            $this->language = DEFAULT_LANGUAGE;
        }
    }

    public function getDebugMode() : bool
    {
        return $this->debugmode ? true : false;
    } // function getDebugMode()

    public function setDebugMode(bool $onOff)
    {
        $this->debugmode = false;
        error_reporting(0);

        /*<!-- build:debug -->*/
        if ($onOff) {
            $this->debugmode = $onOff; // Debug mode enabled or not

            error_reporting(E_ALL);

            $aeDebug = \MarkNotes\Debug::getInstance();
            $aeDebug->enable();

            $aeJSON = \MarkNotes\JSON::getInstance();
            $aeJSON->debug(true);
        } // if ($onOff)
        /*<!-- endbuild -->*/
    }

    public function getDevMode() : bool
    {
        return $this->devmode ? true : false;
    }

    /**
     * Set the developper mode
     *
     * @param bool $onOff
     */
    public function setDevMode(bool $onOff)
    {
        $this->devmode = $onOff;

        $aeFiles = \MarkNotes\Files::getInstance();
        $aeFunctions = \MarkNotes\Functions::getInstance();

        /*<!-- build:debug -->*/
        // Only when the development mode is enabled, include php_error library to make life easier
        if ($onOff) {
            if ($aeFiles->fileExists($lib = $this->getFolderLibs().'php_error'.DS.'php_error.php')) {
                // Seems to not work correctly with ajax; the return JSON isn't correctly understand by JS
                $options = array(
                  // Don't enable ajax is not ajax call
                  'catch_ajax_errors' => $aeFunctions->isAjaxRequest(),
                  // Don't allow to modify sources from php-error
                  'enable_saving' => 0,
                   // Capture everything
                  'error_reporting_on' => 1
                );

                include $lib;
                \php_error\reportErrors($options);
            }
        } // if ($onOff)
        /*<!-- endbuild -->*/
    } // function setDevMode()

    /**
     * The application root folder (due to the use of symbolic links, the .php source files can
     * be in an another folder than the website itself
     *
     * @return string
     */
    public function getFolderAppRoot() : string
    {
        return $this->_folderAppRoot;
    }

    public function setFolderAppRoot($folder)
    {

        // Respect OS directory separator
        $folder = str_replace('/', DS, $folder);

        // Be sure that there is a slash at the end
        $folder = rtrim($folder, DS).DS;

        $this->_folderAppRoot = $folder;
    }

    /**
     * Return the name of the folder (relative) of the documents folder
     *
     * @param  bool $absolute Return the full path (f.i. 'C:\Repository\notes\docs\') if True, the relative one (f.i. 'docs') if False
     *                         the relative one (f.i. 'docs') if False
     * @return string
     */
    public function getFolderDocs(bool $absolute = true) : string
    {
        return ($absolute?$this->getFolderWebRoot():'').$this->_folderDocs;
    } // function getFolderDocs

    public function setFolderDocs($folder)
    {

        // Respect OS directory separator
        $folder = str_replace('/', DS, $folder);

        // Be sure that there is a slash at the end
        $folder = rtrim($folder, DS).DS;

        $this->_folderDocs = $folder;
    } // function setFolderDocs

    /**
     * Return the path to the tmp folder at the webroot. If the folder doesn't exist yet, create it
     *
     * @return string
     */
    public function getFolderTmp() : string
    {
        $folder = $this->_folderWebRoot.'tmp'.DS;

        if (!is_dir($folder)) {
            mkdir($folder, 0755);
        }

        if (!file_exists($fname = $folder.'.gitignore')) {
            file_put_contents($fname, '# Ignore everything'.PHP_EOL.'*');
        }

        if (!file_exists($fname = $folder.'.htaccess')) {
            file_put_contents($fname, 'deny from all');
        }
        return $folder;
    }

    /**
     * Return the root folder of the website (f.i. 'C:\Repository\notes\')
     *
     * @return string
     */
    public function getFolderWebRoot() : string
    {
        return $this->_folderWebRoot;
    }

    public function setFolderWebRoot(string $folder)
    {
        $this->_folderWebRoot = rtrim($folder, DS).DS;
    } // function setFolderWebRoot()

    public function getFolderLibs() : string
    {
        return $this->getFolderAppRoot().'libs'.DS;
    }

    public function getFolderTasks() : string
    {
        return $this->getFolderAppRoot().'classes'.DS.'tasks'.DS;
    }

    public function getFolderTemplates() : string
    {
        return $this->getFolderAppRoot().'templates'.DS;
    }

    /**
     * Return the template to use for the screen display or the html output.
     * Get the template info from the settings.json when the node 'templates' is found there
     *
     * @param  string $default Default filename with extension (f.i. 'screen' or 'html')
     * @return string           Full path to the file
     */
    public function getTemplateFile(string $default = 'screen') : string
    {
        $tmpl = $default;
        if (isset($this->_json['templates'])) {
            if (isset($this->_json['templates'][$default])) {
                $tmpl = $this->sanitizeFileName($this->_json['templates'][$default]);
            }
        }

        if ($tmpl !== '') {
            $fname = $tmpl;

            $aeFiles = \MarkNotes\Files::getInstance();

            if (!$aeFiles->fileExists($fname = $this->getFolderTemplates().$tmpl.'.php')) {
                // The specified template doesn't exists. Back to the default one;
                if ($this->getDebugMode()) {
                    echo '<span style="font-size:0.8em;">Debug | '.__FILE__.'::'.__LINE__.'</span>&nbsp;-&nbsp;';
                }
                echo '<strong><em>Template ['.$fname.'] not found, please review your settings.json file.</em></strong>';
                $fname = '';
            }
        } else { // if ($tmpl!=='')

            $fname = $this->getFolderTemplates().$tmpl.'.php';
        } // if ($tmpl!=='')

            return $fname;
    } // function getTemplateFile()

        /**
         * Use browser's cache or not depending on settings.json
         *
         * @return bool
         */
    public function getOptimisationUseBrowserCache() : bool
    {
        $bReturn = false;

        if (isset($this->_json['optimisation'])) {
            $tmp = $this->_json['optimisation'];
            if (isset($tmp['browser_cache'])) {
                $bReturn = (($tmp['browser_cache'] == 1)?true:false);
            }
        }
        return $bReturn;
    }

    /**
     * Use server's session
     *
     * @return bool
     */
    public function getOptimisationUseServerSession() : bool
    {
        $bReturn = false;

        if (isset($this->_json['optimisation'])) {
            $tmp = $this->_json['optimisation'];
            if (isset($tmp['server_session'])) {
                $bReturn = (($tmp['server_session'] == 1)?true:false);
            }
        }
        return $bReturn;
    }

    /**
     * Use lazyload or not depending on settings.json
     *
     * @return bool
     */
    public function getOptimisationLazyLoad() : bool
    {
        $bReturn = false;

        if (isset($this->_json['optimisation'])) {
            $tmp = $this->_json['optimisation'];
            if (isset($tmp['lazyload'])) {
                $bReturn = (($tmp['lazyload'] == 1)?true:false);
            }
        }

        return $bReturn;
    } // function getOptimisationLazyLoad()

    /**
     * Return the Google font to use (from settings.json)
     *
     * @param  bool $css If true, return a css block for using the font(s).  If false, return the font(s) name
     * @return string
     */
    public function getPageGoogleFont(bool $css = true) : string
    {
        $return = '';

        if (isset($this->_json['page'])) {
            if (isset($this->_json['page']['google_font'])) {
                $font = str_replace(' ', '+', $this->_json['page']['google_font']);

                if ($css === true) {
                    if ($font !== '') {
                        $result = '<link href="https://fonts.googleapis.com/css?family='.$font.'" rel="stylesheet">';

                        $i = 0;
                        $return = '<style>';
                        $sFontName = str_replace('+', ' ', $font);
                        for ($i = 1; $i < 7; $i++) {
                            $return .= 'page h'.$i.'{font-family:"'.$sFontName.'";}';
                        }
                        $return .= '</style>';
                    } // if ($font!=='')
                } else { // if ($css===true)
                    $return = $font;
                } // if ($css===true)
            }
        } // if (isset($this->_json['page']))

        return $return;
    } // function getPageGoogleFont()

    /**
     * Return the max width size for images (from settings.json)
     *
     * @return string
     */
    public function getPageImgMaxWidth() : string
    {
        $return = IMG_MAX_WIDTH;

        if (isset($this->_json['page'])) {
            if (isset($this->_json['page']['img_maxwidth'])) {
                $return = trim($this->_json['page']['img_maxwidth']);
            }
        }

        return $return;
    } // function getPageImgMaxWidth()

    /**
     * Return the value for the robots info in the header
     *
     * @return string
     */
    public function getPageRobots() : string
    {
        $return = 'index, follow';

        if (isset($this->_json['page'])) {
            if (isset($this->_json['page']['robots'])) {
                $return = trim($this->_json['page']['robots']);
            }
        }

        return $return;
    } // function getPageRobots()

    /**
     * Return the name of the website
     *
     * @return string
     */
    public function getSiteName() : string
    {
        $sReturn = '';

        if (isset($this->_json['site_name'])) {
            $sReturn = trim($this->_json['site_name']);
        }

        return $sReturn;
    }

    /**
     * Return the type of slideshow to use : reveal or remark
     *
     * @return string
     */
    public function getSlideshowType(string $sDefault = 'reveal') : string
    {
        $sReturn = $sDefault;
        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow']['type'])) {
                $sReturn = trim($this->_json['slideshow']['type']);
            }
        }

        return $sReturn;
    }

    /**
     * Reveal allows to foreseen animations between slides.  The animations will be read from the settings.json files
     * under the slideshow->reveal->animation entry
     */
    public function getSlideshowAnimations(string $sType = 'reveal') : array
    {
        $sType = 'reveal';
        $arr = array('h1' => 'zoom','h2' => 'concave','h3' => 'slide-in','h4' => 'fade','h5' => 'fade','h6' => 'fade');

        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow'][$sType])) {
                if (isset($this->_json['slideshow'][$sType]['animation'])) {
                    $arr = $this->_json['slideshow'][$sType]['animation'];
                }
            }
        }

        return $arr;
    }

    /**
     * Return the bullet to use for slideshow lists (f.i. "check" (will be used as fa-check)
     * with Font-Awesome)
     */
    public function getSlideshowListBullet(string $sDefault = 'check') : string
    {
        $sReturn = $sDefault;

        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow']['bullet'])) {
                if (isset($this->_json['slideshow']['bullet']['fontawesome'])) {
                    $sReturn = $this->_json['slideshow']['bullet']['fontawesome'];
                }
            }
        }

        return $sReturn;
    }

    /**
     * Return the bullet to use for slideshow lists (f.i. "check" (will be used as fa-check)
     * with Font-Awesome)
     */
    public function getSlideshowListBulletExtra(string $sDefault = '') : string
    {
        $sReturn = $sDefault;

        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow']['bullet'])) {
                if (isset($this->_json['slideshow']['bullet']['extra_attribute'])) {
                    $sReturn = $this->_json['slideshow']['bullet']['extra_attribute'];
                }
            }
        }

        return $sReturn;
    }

    /**
     * With reveal, when an image is used as background for the slide, check if a maximum size (like 800px 600px) has
     * been specified in the settings.json and if so, return that definition
     */
    public function getSlideshowExtraImgAttributes(string $sType = 'reveal') : string
    {
        $sType = 'reveal';
        $sReturn = '';

        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow'][$sType])) {
                if (isset($this->_json['slideshow'][$sType]['section'])) {
                    if (isset($this->_json['slideshow'][$sType]['section']['extra_data_img_attr'])) {
                        $sReturn = $this->_json['slideshow'][$sType]['section']['extra_data_img_attr'];
                    }
                }
            }
        }

        return $sReturn;
    }

    /**
     * Return the type of animation for bullets : normal (no animation) or animated (fragrent for reveal)
     * under the slideshow->reveal->animation->bullet entry
     *
     * @return string
     */
    public function getSlideshowBullet(string $sType = 'reveal') : string
    {
        $sReturn = 'animated';
        if (isset($this->_json['slideshow'])) {
            if (isset($this->_json['slideshow'][$sType])) {
                if (isset($this->_json['slideshow'][$sType]['animation'])) {
                    if (isset($this->_json['slideshow'][$sType]['animation']['bullet'])) {
                        $sReturn = $this->_json['slideshow'][$sType]['animation']['bullet'];
                    }
                }
            }
        }

        return $sReturn;
    }

    /**
     * Max allowed size for the search string
     *
     * @return int
     */
    public function getSearchMaxLength() : int
    {
        return SEARCH_MAX_LENGTH;
    }

    /**
     * Retrieve if a specific tool like for instance 'decktape' is configured in the settings.json file
     */
    public function getTools(string $sTool) : string
    {
        $aeFiles = \MarkNotes\Files::getInstance();

        $sType = 'reveal';
        $sReturn = '';

        if (isset($this->_json['tools'])) {
            if (isset($this->_json['tools'][$sTool])) {
                $sTool = $this->_json['tools'][$sTool];
                if ($aeFiles->fileExists($sTool)) {
                    $sReturn = $sTool;
                }
            }
        }

        return $sReturn;
    }
    /**
     * Tags to automatically select when displaying the page
     *
     * @return string
     */
    public function getTagsAutoSelect() : string
    {
        $sReturn = '';
        if (isset($this->_json['tags'])) {
            $sReturn = implode($this->_json['tags'], ",");
        }
        return $sReturn;
    }

    /**
     * Prefix to use to indicate a word as a tag
     *
     * @return string
     */
    public function getTagPrefix() : string
    {
        $sReturn = PREFIX_TAG;
        if (isset($this->_json['tag_prefix'])) {
            $sReturn = trim($this->_json['tag_prefix']);
        }

        return $sReturn;
    }

    /**
     * Should nodes of the treeview be opened at loading time ?
     *
     * @return bool
     */
    public function getTreeOpened() : bool
    {
        $bReturn = false;

        if (isset($this->_json['list'])) {
            if (isset($this->_json['list']['opened'])) {
                $bReturn = ($this->_json['list']['opened'] == 1?true:false);
            }
        }

        return $bReturn ? true : false;
    } // function getTreeOpened()

    /**
     * List of folders that should be immediately opened
     *
     * @return array
     */
    public function getTreeAutoOpen() : array
    {
        $arr = array();

        if (isset($this->_json['list'])) {
            if (isset($this->_json['list']['auto_open'])) {
                foreach ($this->_json['list']['auto_open'] as $folder) {
                    // Respect OS directory separator
                    $folder = rtrim(str_replace('/', DS, $folder), DS);
                    // List of folders that should be immediatly opened
                    $arr[] = $this->getFolderDocs(true).$folder;
                }
            }
        } // if(isset($this->_json['list']))

        return $arr;
    }

    /**
     * Return the password used for encryptions
     *
     * @return string
     */
    public function getEncryptionPassword() : string
    {
        $sReturn = '';

        if (isset($this->_json['encryption'])) {
            if (isset($this->_json['encryption']['password'])) {
                $sReturn = trim($this->_json['encryption']['password']);
            }
        }

        return $sReturn;
    } // function getEncryptionPassword()

    /**
     * Return the method used for encryptions
     *
     * @return string
     */
    public function getEncryptionMethod() : string
    {
        $sReturn = 'aes-256-ctr';

        if (isset($this->_json['encryption'])) {
            if (isset($this->_json['encryption']['method'])) {
                $sReturn = trim($this->_json['encryption']['method']);
                if ($sReturn === '') {
                    $sReturn = 'aes-256-ctr';
                }
            }
        }

        return $sReturn;
    } // function getEncryptionMethod()

    /**
     * Get locale
     *
     * @return bool
     */
    public function getLocale() : string
    {
        $sReturn = 'en_GB';

        if (isset($this->_json['locale'])) {
            // Be sure to have en-US (minus) and not en_US (underscore)
            $sReturn = str_replace('_', '-', trim($this->_json['locale']));
        }

        return $sReturn;
    } // function getEditAllowed()
    /**
     * Allow editions ?
     *
     * @return bool
     */
    public function getEditAllowed() : bool
    {
        $bReturn = EDITOR;

        if (isset($this->_json['editor'])) {
            $bReturn = ($this->_json['editor'] == 1?true:false);
        }

        return $bReturn;
    } // function getEditAllowed()

    public function getchmod(string $type = 'folder') : int
    {
        return ($type === 'folder' ? CHMOD_FOLDER : CHMOD_FILE);
    } // function getchmod()

    /**
     * JoliTypo is Web Microtypography fixer (https://github.com/jolicode/JoliTypo)
     * and can solve common typo issues.
     *
     * @return bool
     */
    public function getUseJoliTypo() : bool
    {
        $bReturn = true;

        if (isset($this->_json['page'])) {
            $tmp = $this->_json['page'];
            if (isset($tmp['jolitypo'])) {
                $bReturn = (($tmp['jolitypo'] == 1)?true:false);
            }
        }
        return $bReturn;
    }


    /**
     * Can we use the navigator localStorage cache system ?
     *
     * @return bool
     */
    public function getUseLocalCache() : bool
    {
        $bReturn = true;

        if (isset($this->_json['optimisation'])) {
            $tmp = $this->_json['optimisation'];
            if (isset($tmp['localStorage'])) {
                $bReturn = (($tmp['localStorage'] == 1)?true:false);
            }
        }
        return $bReturn;
    }
}
