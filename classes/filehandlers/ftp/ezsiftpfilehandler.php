<?php

class eZSIFTPFileHandler extends eZSIFileHandler
{
    private function eZSIFTPFileHandler()
    {
    }

    private function connect()
    {
        if( is_resource( $this->ConnectionResource ) )
        {
            eZDebug::writeError( 'No Connexion Resource available', 'eZSIFTPFileHandler::connect' )
            return false;
        }

        $ini               = eZINI::instance( 'ezsi.ini' );
        $host              = $ini->variable( 'FTPSettings', 'Host' );
        $port              = $ini->variable( 'FTPSettings', 'Port' );
        $timeout           = $ini->variable( 'FTPSettings', 'Timeout' );
        $login             = $ini->variable( 'FTPSettings', 'Login' );
        $password          = $ini->variable( 'FTPSettings', 'Password' );
        $destinationFolder = $ini->variable( 'FTPSettings', 'DestinationFolder' );

        if( $cr = @ftp_connect( $host, $port, $timeout ) and ftp_login( $cr, $login, $password ) )
        {
            eZDebug::writeNotice( 'Connecting to FTP server', 'eZSIFTPFileHandler' );

            $this->ConnectionResource = $cr;
            $GLOBALS['eZSIFTPFileHandler'] = $this;
            unset( $cr );

            // creating basic stucture if does not exists
            // the directory does not exists
            if( !@ftp_chdir( $this->ConnectionResource, $destinationFolder ) )
            {
                // create it
                //if( !@ftp_mkdir( $this->ConnectionResource, 'si-blocks' ) )
                if( !$this->mkDir( $destinationFolder ) )
                {
                    eZDebug::writeError( 'Unable to create dir ' . $destinationFolder, 'eZSIFTPFileHandler::eZSIFTPFileHandler' );
                }

                // dir should exists now
                eZDebug::writeNotice( 'CWD : ' . ftp_pwd( $this->ConnectionResource), 'eZSIFTPFileHandler::eZSIFTPFileHandler' );
                ftp_chdir( $this->ConnectionResource, $destinationFolder );
            }


            // make sure the connection is closed at the
            // end of the script
            eZExecution::addCleanupHandler( 'eZSIFTPCloseConnexion' );

            return true;
        }
        else
        {
            eZDebug::writeError( 'Unable to connect to FTP server', 'eZSIFTPFileHandler' );

            return false;
        }
    }

    private function mkDir( $path )
    {
        $dirList = explode( "/", $path );
        $path = "";

        foreach( $dirList as $dir )
        {
            $path .= "/" . $dir;

            if(!@ftp_chdir( $this->ConnectionResource, $path) )
            {
                @ftp_chdir( $this->ConnectionResource, "/" );

                if( !@ftp_mkdir( $this->ConnectionResource, $path ) )
                {
                    return false;
                }

                eZDebug::writeNotice( 'Creating ' . $path, 'eZSIFTPFileHandler::mkDir' );
            }
        }

        // returning to root folder : lots of moves but more clean
        ftp_chdir( $this->ConnectionResource, "/" );

        return true;
    }

    public static function instance()
    {
        if( isset( $GLOBALS['eZSIFTPFileHandler'] ) and is_object( $GLOBALS['eZSIFTPFileHandler'] ) )
        {
            return $GLOBALS['eZSIFTPFileHandler'];
        }
        else
        {
            return new eZSIFTPFileHandler();
        }
    }

    public function storeFile( $directory, $fileName, $fileContents )
    {
        if( !self::connect() )
        {
            return false;
        }

        // must write the file locally and then upload it :'(
        // $tmpFileDir  = 'var/cache ';
        $ini    = eZINI::instance( 'site.ini' );
        $tmpDir = $ini->variable( 'FileSettings', 'TemporaryDir' );

        $tmpFileName = md5( uniqid( rand(), true ) );
        $tmpFilePath = $tmpDir . '/' . $tmpFileName;

        eZFile::create( $tmpFileName, $tmpDir, $fileContents );

        if( !ftp_put( $this->ConnectionResource, $fileName, $tmpFilePath, FTP_BINARY ) )
        {
            eZDebug::writeError( 'Unable to upload the file', 'eZSIFTPFileHandler::storeFile' );
            return false;
        }

        // removes the temp file
        @unlink( $tmpFilePath );

        return true;
    }

    public function removeFile( $directory, $fileName )
    {
        if( !self::connect() )
        {
            return false;
        }

        if( !ftp_delete( $this->ConnectionResource, $fileName ) )
        {
            eZDebug::writeError( 'Unable to delete file', 'eZSIFTPFileHandler::removeFile' );
            return false;
        }

        return true;
    }

    public function close()
    {
        return @ftp_close( $this->ConnectionResource );
    }

    var $ConnectionResource = false;
}

function eZSIFTPCloseConnexion()
{
    $eZSIFTP = eZSIFTPFileHandler::instance();
    $eZSIFTP->close();
}
?>

