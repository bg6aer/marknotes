<?php
/**
* markdown - Script that will transform your notes taken in the Markdown format (.md files) into a rich website
* @version   : 1.0.5
* @author    : christophe@aesecure.com
* @license   : MIT
* @url       : https://github.com/cavo789/markdown
* @package   : 2017-03-20T20:44:52.373Z
*/?>
<?php

namespace AeSecureMDTasks;

class Edit
{
    public static function run(array $params)
    {

        $aeDebug=\AeSecure\Debug::getInstance();
        $aeSettings=\AeSecure\Settings::getInstance();

        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // If the filename doesn't mention the file's extension, add it.
        if (substr($params['filename'], -3)!='.md') {
            $params['filename'].='.md';
        }

        $fullname=str_replace('/', DIRECTORY_SEPARATOR, utf8_decode($aeSettings->getFolderDocs(true).ltrim($params['filename'], DS)));

        if (!\AeSecure\Files::fileExists($fullname)) {
            echo str_replace('%s', '<strong>'.$fullname.'</strong>', $aeSettings->getText('file_not_found', 'The file [%s] doesn\\&#39;t exists'));
            die();
        }

        $markdown=file_get_contents($fullname);

        // Initialize the encryption class
        $aesEncrypt=new \AeSecure\Encrypt($aeSettings->getEncryptionPassword(), $aeSettings->getEncryptionMethod());

        list($bReturn, $markdown)=$aesEncrypt->HandleEncryption($fullname, $markdown, true);

        $sReturn='<div class="editor-wrapper"><strong class="filename">'.utf8_encode($fullname).'</strong><textarea id="sourceMarkDown" placeholder="Content here ....">'.$markdown.'</textarea></div>';

        header('Content-Type: text/plain; charset=utf-8');
        echo $sReturn;

        die();
    } // function Run()
} // class Edit