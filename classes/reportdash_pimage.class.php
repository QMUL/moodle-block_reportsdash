<?php
/**
 * Created by PhpStorm.
 * User: nigel.daley
 * Date: 08/05/2015
 * Time: 15:00
 */

global  $CFG;

require_once($CFG->dirroot."/blocks/reportsdash/classes/pchart/class/pImage.class.php");

class block_reportsdash_reportdash_pimage extends pImage  {

    /* Render the picture to a web browser stream */
    function stroke($BrowserExpire=FALSE)
    {
        if ( $this->TransparentBackground ) { imagealphablending($this->Picture,false); imagesavealpha($this->Picture,true); }

        ob_start();
         imagepng($this->Picture);

        $image  =   ob_get_contents();

        ob_end_clean();

        return $image;
    }

    /* Dump the image map */
    function dumpImageMap($Name="pChart",$StorageMode=IMAGE_MAP_STORAGE_SESSION,$UniqueID="imageMap",$StorageFolder="tmp")
    {
        $imagemap   =   "";

        $this->ImageMapIndex 		= $Name;
        $this->ImageMapStorageMode		= $StorageMode;

        if ( $this->ImageMapStorageMode == IMAGE_MAP_STORAGE_SESSION )
        {
            if(!isset($_SESSION)) { session_start(); }
            if ( $_SESSION[$Name] != NULL )
            {
                ob_start();
                foreach($_SESSION[$Name] as $Key => $Params)
                { echo $Params[0].IMAGE_MAP_DELIMITER.$Params[1].IMAGE_MAP_DELIMITER.$Params[2].IMAGE_MAP_DELIMITER.$Params[3].IMAGE_MAP_DELIMITER.$Params[4]."\r\n"; }

                $imagemap  =   ob_get_contents();
                ob_end_clean();
            }
        }
        elseif( $this->ImageMapStorageMode == IMAGE_MAP_STORAGE_FILE )
        {
            if (file_exists($StorageFolder."/".$UniqueID.".map"))
            {
                $Handle = @fopen($StorageFolder."/".$UniqueID.".map", "r");
                if ($Handle) { while (($Buffer = fgets($Handle, 4096)) !== false) { echo $Buffer; } }
                fclose($Handle);

                if ( $this->ImageMapAutoDelete ) { unlink($StorageFolder."/".$UniqueID.".map"); }
            }
        }

        return $imagemap;

        /* When the image map is returned to the client, the script ends */
//        exit();
    }

}