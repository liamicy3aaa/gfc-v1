<?php

/**
* Cipher Class
* Basic cipher class allowing us to encrypt and decrypt a string with a private key
* Author: Liam McClelland
* Copyright: 2019 © Gadgetfreak Systems.
*/

class cipher {
    
    /**
    * Encrypt Function
    * Pass a string to be encrypted 
    * 
    * @param mixed $string
    * @param mixed $action
    * @return mixed string
    */
    
    public static function encrypt($string, $action = "e") {
        
        // Getting private key
        $key = parse_ini_file("../app/keys.ini")["key"];
        
        // IV for encryption / decryption
        $secret_iv = "RANDOM_KEY_TO_IV";
 
        $output = false;
        
        // Encryption method
        $encrypt_method = "AES-256-CBC";
        
        // Generating IV
        $key = hash( 'sha256', $key );
        $iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );
     
        if( $action == 'e' ) {
          
          // ENCRYPT
            
            $output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
        
        } elseif( $action == 'd' ) {
            
            // DECRYPT
            
            $output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
            
        }
        
        return $output;
    }
    
    /**
    * Decrypt Function
    * Decrypts a string that has already been encrypted. It simply uses the encrypt function but passes the decrypt command.
    * 
    * @param mixed $string
    * @return mixed string
    */
    
    public static function decrypt($string) {
        
       return self::encrypt($string, "d");
        
    }
    
    
}