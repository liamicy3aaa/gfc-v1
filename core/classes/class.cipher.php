<?php

/**
 * Class cipher
 *
 * @author Liam McClelland
 */
class cipher {
    
    /**
    * Encrypt
    *
    * Encrypt a string to prevent the true value from being readable to the human eye.
    *
    * @param string $string Value you wish to encrypt
    * @param string $action Choose whether you wish to encrypt or decrypt the provided input.
    * @return string
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
    * Decrypt
    *
    * Decrypt an encrypted string to reveal the true value.
    *
    * @param string $string
    * @return string
    */
    
    public static function decrypt($string) {
        
       return self::encrypt($string, "d");
        
    }
    
    
}