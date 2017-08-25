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
  public $version = "1.0";
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
  
  public $CANDLELIGHTING = '[CANDLELIGHTING]';
  public $PARSHA = '[PARSHA]';
  public $shabbos_tablename = "shabbostimes";

    function shabbostimes(){
        parent::phplistplugin();
        $this->coderoot = dirname(__FILE__).'/shabbostimes/';
        $this->create_db_table();
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

    function parse_hebcaldata($hebcal_data){
        $parsha = "Parsha not available";
        $candlelighting = "Candlelighting not available";
        $candlelighting_date = NULL;
        
        foreach ($hebcal_data["items"] as $item) {
            if ($item['category'] == 'parashat'){
                // Get this weeks Parsha and the date
                $parsha_string = $item["title"];
                $parsha_exploded = explode('Parshas ', $parsha_string, 2);
                $parsha = $parsha_exploded[1];
            }
            else if ($item['category'] == 'candles'){
                $candlelighting_string = $item["title"];
                $candlelighting_exploded = explode(': ', $candlelighting_string, 2);
                $candlelighting = $candlelighting_exploded[1];

                $candlelighting_date = date('Y-m-d', strtotime($item["date"]));
            }
        }
        return array ($parsha, $candlelighting, $candlelighting_date);
    }

    function create_db_table(){
        // Create the table if it doesn't exist
        $shabbos_tablestructure = array(
                        "zipcode" => array("varchar(5) not null","zipcode"),
                        "candlelighting_date" => array("DATE","Date of lighting candles"),
                        "candlelighting" => array("varchar(80) not null","Time of Candlelighting"),
                        "parsha" => array("varchar(80) not null","Parsha")
                    );

        $req = Sql_Query(sprintf('select table_name from information_schema.tables where table_schema = "'.$GLOBALS['database_name'].'" AND table_name="%s"', $this->shabbos_tablename));
        if (! Sql_Fetch_Row($req)) {
            Sql_create_Table ($this->shabbos_tablename,$shabbos_tablestructure);
        }

    }

    function replace($content){
        // If the strings to replace aren't in the text, there's nothing to do.
        if ( (strpos($content, $this->CANDLELIGHTING) === FALSE) && (strpos($content, $this->PARSHA) === FALSE) )
            return $content;

        $zipcode = getConfig('shabbostimes_zipcode');
        if(!$zipcode){
          // Error, but we can't do anything.
          return $content;
        }

        $parsha = NULL;
        $candlelighting = NULL;

        // First see if we already have the info in the DB.
        $today = date('Y-m-d');

        $query_result = Sql_Query(sprintf('SELECT parsha, candlelighting FROM %s
                            WHERE (zipcode="%s" AND DATE(candlelighting_date) >= DATE("%s"))',
                            $this->shabbos_tablename,
                            $zipcode,
                            $today));
        if ($result = Sql_Fetch_Array($query_result)){
            // Found in DB
            $parsha = $result[0];
            $candlelighting = $result[1];
        }
        else{
            // Not in the DB. Need to get it from hebcal
            $hebcal_data = $this->get_hebcal_data($zipcode);
            list ($parsha, $candlelighting, $candlelighting_date) = $this->parse_hebcaldata($hebcal_data);
            // Store it for next time
            Sql_Query(sprintf('INSERT INTO %s (zipcode, candlelighting_date, parsha, candlelighting)
                                VALUES
                                ("%s", "%s", "%s", "%s")',
                            $this->shabbos_tablename,
                            $zipcode,
                            $candlelighting_date,
                            $parsha,
                            $candlelighting));
        }

        // Now that $parsha and $candlelighting are set:
        $content = str_replace($this->CANDLELIGHTING, $candlelighting, $content);
        $content = str_replace($this->PARSHA, $parsha, $content);
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
    return $this->replace($content);
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
    return $this->replace($content);
  }
}
?>
