<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class siphons
 */
class siphons {
    /**
     * @var
     */
    var $config;
    /**
     * @var
     */
    var $discord;
    /**
     * @var
     */
    var $logger;
    /**
     * @var
     */
    var $toDiscordChannel;
    protected $keyID;
    protected $vCode;
    protected $prefix;

    /**
     * @param $config
     * @param $discord
     * @param $logger
     */
    function init($config, $discord, $logger)
    {
        $this->config = $config;
        $this->discord = $discord;
        $this->logger = $logger;
        $this->toDiscordChannel = $config["plugins"]["siphons"]["channelID"];
        $this->keyID = $config["plugins"]["siphons"]["keyID"];
        $this->vCode = $config["plugins"]["siphons"]["vCode"];
        $this->prefix = empty($config["plugins"]["siphons"]["prefix"]);
        $lastCheck = getPermCache("siphonLastChecked{$this->keyID}");
        if ($lastCheck == NULL) {
            // Schedule it for right now if first run
            setPermCache("siphonLastChecked{$this->keyID}", time() - 5);
        }
    }

    /**
     *
     */
    function tick()
    {
        $lastChecked = getPermCache("siphonLastChecked{$this->keyID}");
        $keyID = $this->keyID;
        $vCode = $this->vCode;

        if ($lastChecked <= time()) {
            $this->logger->info("Checking API Key {$keyID} for siphons");
            $this->checkTowers($keyID, $vCode);
            //6
            setPermCache("siphonLastChecked{$keyID}", time() + 21660);
        }
    }

    function checkTowers($keyID, $vCode)
    {
        $url = "https://api.eveonline.com/corp/AssetList.xml.aspx?keyID={$keyID}&vCode={$vCode}";
        $xml = simplexml_load_file($url);

        foreach ($xml->result->rowset->row as $structures){
            //Check silos
            if ($structures->attributes()->typeID == 14343){
                foreach ($structures->rowset->row as $silo){
                    //Avoid reporting empty silos
                    if ($silo->attributes()->quantity != 0){
                        //Check for a multiple of 100
                        if ($silo->attributes()->quantity % 100 != 0) {
                            $systemName = dbQueryField("SELECT solarSystemName FROM mapSolarSystems WHERE solarSystemID = :id", "solarSystemName", array(":id" => $structures->attributes()->locationID), "ccp");
                            $msg = "{$this->prefix}";
                            $msg .= "**POSSIBLE SIPHON**\n";
                            $msg .= "**System: **{$systemName}\n";
                            // Send the mails to the channel
                            $this->discord->api("channel")->messages()->create($this->toDiscordChannel, $msg);
                            $this->logger->info($msg);
                            sleep(2); // Lets sleep for a second, so we don't rage spam
                        }
                    }
                }
            }
        }
        $this->logger->info("Siphon Check Complete");
        return null;
    }

    /**
     *
     */
    function onMessage()
    {
    }

    /**
     * @return array
     */
    function information()
    {
        return array(
            "name" => "",
            "trigger" => array(""),
            "information" => ""
        );
    }
}
