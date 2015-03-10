<?php
/**
 * ShabbosTimes plugin for phplist
 * 
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * @category  phplist
 * @package   ShabbosTimes
 * @author    Moshe Kaplan
 * @license   http://www.gnu.org/licenses/gpl.html GNU General Public License, Version 3
 */
 
class shabbostimes extends phplistPlugin
{
  public $name = "ShabbosTimes plugin for phpList";
  public $coderoot = "shabbostimes/";
  public $version = "0.3";
  public $description = 'Replaces [CANDLELIGHTING] and [PARSHA] with the candlelighting and parsha';
  public $settings = array(
    "shabbostimes_zipcode" => array (
      'value' => "",
      'description' => "Zipcode to use for zmanim",
      'type' => "text",
      'allowempty' => 0,
      "max" => 1000,
      "min" => 0,
      'category'=> 'ShabbosTimes',
    ),
  );
  
    function shabbostimes(){
        parent::phplistplugin();
        $this->coderoot = dirname(__FILE__).'/shabbostimes/';
    }
    
    function activate(){
        return true;
    }
    
    function get_hebcal_data($zipcode){
      // Retrieves the data from hebcal
      // http://www.hebcal.com/home/197/shabbat-times-rest-api
      $hebcal_url = 'http://www.hebcal.com/shabbat/?cfg=json&geo=zip&zip='.$zipcode.'&m=0&a=on';
      
      // http://stackoverflow.com/questions/16700960/how-to-use-curl-to-get-json-data-and-decode-the-data
      // Will dump a beauty json :3
      //  Initiate curl
      $ch = curl_init();
      // Disable SSL verification
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      // Will return the response, if false it print the response
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // Set the url
      curl_setopt($ch, CURLOPT_URL,$hebcal_url);
      // Execute
      $result=curl_exec($ch);
      // Closing
      curl_close($ch);
      
      return json_decode($result, true);
      }
      


    function replace($content){
        $zipcode = getConfig('shabbostimes_zipcode');
        if(!$zipcode){
          // Error, but we can't do anything.
          return $content;
        }
        
        $hebcal_data = get_hebcal_data($zipcode);
      
        $parsha = NULL;
        $candlelighting = NULL;
        
        foreach ($hebcal_data["items"] as $item) {
            if ($item['category'] == 'parashat'){
                $parsha_string = $item["title"];
                $parsha = explode('Parshas ', $parsha_string, 2)[1];
            }
            else if ($item['category'] == 'candles'){
                $candlelighting_string = $item["title"];
                $candlelighting = explode(': ', $candlelighting_string, 2)[1];
            }
        }
        // Now that $parsha and $candlelighting are set:
        $content = str_replace("[CANDLELIGHTING]", $candlelighting, $content);
        $content = str_replace("[PARSHA]", $parsha, $content);
        return $content;
    }
    
      /* 
   * parseOutgoingTextMessage
   * @param integer messageid: ID of the message
   * @param string  content: entire text content of a message going out
   * @param string  destination: destination email
   * @param array   userdata: associative array with data about user
   * @return string parsed content
   */
  function parseOutgoingTextMessage($messageid, $content, $destination, $userdata = null) {
    return replace($content);
  }

  /* 
   * parseOutgoingHTMLMessage
   * @param integer messageid: ID of the message
   * @param string  content: entire text content of a message going out
   * @param string  destination: destination email
   * @param array   userdata: associative array with data about user
   * @return string parsed content
   */
  function parseOutgoingHTMLMessage($messageid, $content, $destination, $userdata = null) {
    return replace($content);
  }
}
?>
