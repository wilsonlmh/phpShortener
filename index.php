<?php

/*
Rewrite:
1.
RewriteRule ^index\/(.*)$ index.php{R:1}
2.
RewriteRule ^\?(.*)$  index?{R:1}  //? is a reserved word for URL, DON'T use it for shorten base text
3.
RewriteCond {QUERY_STRING}=^(.*)$
RewriteRule ^([0-9+a-z+A-z+\.\!\*\(\)]+)\/?(.*)$  index.php?go={R:1}&get={UrlEncode:{C:0}}&path={UrlEncode:{R:2}}
*/
$config = array( 
    'siteRoot' => 'http://short.example.com/', //with slash
    'key' => '', //can be empty
    'base' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.!*()', // usable characters, current length = 67
    'length' => 6, //a simple formula to calcute usable space = base.length^length, currently 90,458,382,169
    'dbPath' => './db.json',
    'cachePath' => './cache.json',
    'cacheMax' => 100, //unit: records
    'writeToCacheAfterAdd' => true,
    'urlType' => array (
            //Define how to redirect GET parameters
            'default' => function($inputURL,$get,$path) {
                $urlObj = parse_url($inputURL);
                if (!isset($urlObj['query'])) $urlObj['query'] = "";
                if (!isset($urlObj['path'])) $urlObj['path'] = "";
                
                if (!empty($get)) $urlObj['query'] = $get . '&' . $urlObj['query']; 
                $urlObj['path'] = rtrim($urlObj['path'],'/');
                if (!empty($path)) $urlObj['path'] .= '/' . $path;
                
                return http_build_url("",$urlObj);
            },
            'owncloudFiles' => function($inputURL,$get,$path) {
                $urlObj = parse_url($inputURL);
                if (!isset($urlObj['query'])) $urlObj['query'] = "";
                
                if (!empty($get)) $urlObj['query'] = $get . '&' . $urlObj['query'];
                if (!empty($path)) $urlObj['query'] = 'path=' . urlencode($path) . '&' . $urlObj['query'];
                
                return http_build_url("",$urlObj);
            },
            'owncloudGallary' => function($inputURL,$get,$path) {

                $urlObj = parse_url($inputURL);
                if (!isset($urlObj['query'])) $urlObj['query'] = "";
                if (!isset($urlObj['fragment'])) $urlObj['fragment'] = "";
                
                if (!empty($get)) $urlObj['query'] = $get . '&' . $urlObj['query'];
                if (!empty($path)) $urlObj['fragment'] = urlencode($path);
                return http_build_url("",$urlObj);
            }
            //Note, to prevent running urlencode two time cause error, only the input urlencode() will run if both defined true
        ),
    );


if (!function_exists('http_build_url'))
{
    define('HTTP_URL_REPLACE', 1);              // Replace every part of the first URL when there's one of the second URL
    define('HTTP_URL_JOIN_PATH', 2);            // Join relative paths
    define('HTTP_URL_JOIN_QUERY', 4);           // Join query strings
    define('HTTP_URL_STRIP_USER', 8);           // Strip any user authentication information
    define('HTTP_URL_STRIP_PASS', 16);          // Strip any password authentication information
    define('HTTP_URL_STRIP_AUTH', 32);          // Strip any authentication information
    define('HTTP_URL_STRIP_PORT', 64);          // Strip explicit port numbers
    define('HTTP_URL_STRIP_PATH', 128);         // Strip complete path
    define('HTTP_URL_STRIP_QUERY', 256);        // Strip query string
    define('HTTP_URL_STRIP_FRAGMENT', 512);     // Strip any fragments (#identifier)
    define('HTTP_URL_STRIP_ALL', 1024);         // Strip anything but scheme and host

    // Build an URL
    // The parts of the second URL will be merged into the first according to the flags argument. 
    // 
    // @param   mixed           (Part(s) of) an URL in form of a string or associative array like parse_url() returns
    // @param   mixed           Same as the first argument
    // @param   int             A bitmask of binary or'ed HTTP_URL constants (Optional)HTTP_URL_REPLACE is the default
    // @param   array           If set, it will be filled with the parts of the composed url like parse_url() would return 
    function http_build_url($url, $parts=array(), $flags=HTTP_URL_REPLACE, &$new_url=false)
    {
        $keys = array('user','pass','port','path','query','fragment');

        // HTTP_URL_STRIP_ALL becomes all the HTTP_URL_STRIP_Xs
        if ($flags & HTTP_URL_STRIP_ALL)
        {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
            $flags |= HTTP_URL_STRIP_PORT;
            $flags |= HTTP_URL_STRIP_PATH;
            $flags |= HTTP_URL_STRIP_QUERY;
            $flags |= HTTP_URL_STRIP_FRAGMENT;
        }
        // HTTP_URL_STRIP_AUTH becomes HTTP_URL_STRIP_USER and HTTP_URL_STRIP_PASS
        else if ($flags & HTTP_URL_STRIP_AUTH)
        {
            $flags |= HTTP_URL_STRIP_USER;
            $flags |= HTTP_URL_STRIP_PASS;
        }

        // Parse the original URL
        $parse_url = parse_url($url);

        // Scheme and Host are always replaced
        if (isset($parts['scheme']))
            $parse_url['scheme'] = $parts['scheme'];
        if (isset($parts['host']))
            $parse_url['host'] = $parts['host'];

        // (If applicable) Replace the original URL with it's new parts
        if ($flags & HTTP_URL_REPLACE)
        {
            foreach ($keys as $key)
            {
                if (isset($parts[$key]))
                    $parse_url[$key] = $parts[$key];
            }
        }
        else
        {
            // Join the original URL path with the new path
            if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH))
            {
                if (isset($parse_url['path']))
                    $parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
                else
                    $parse_url['path'] = $parts['path'];
            }

            // Join the original query string with the new query string
            if (isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY))
            {
                if (isset($parse_url['query']))
                    $parse_url['query'] .= '&' . $parts['query'];
                else
                    $parse_url['query'] = $parts['query'];
            }
        }

        // Strips all the applicable sections of the URL
        // Note: Scheme and Host are never stripped
        foreach ($keys as $key)
        {
            if ($flags & (int)constant('HTTP_URL_STRIP_' . strtoupper($key)))
                unset($parse_url[$key]);
        }


        $new_url = $parse_url;

        return 
             ((isset($parse_url['scheme'])) ? $parse_url['scheme'] . '://' : '')
            .((isset($parse_url['user'])) ? $parse_url['user'] . ((isset($parse_url['pass'])) ? ':' . $parse_url['pass'] : '') .'@' : '')
            .((isset($parse_url['host'])) ? $parse_url['host'] : '')
            .((isset($parse_url['port'])) ? ':' . $parse_url['port'] : '')
            .((isset($parse_url['path'])) ? $parse_url['path'] : '')
            .((isset($parse_url['query'])) ? '?' . $parse_url['query'] : '')
            .((isset($parse_url['fragment'])) ? '#' . $parse_url['fragment'] : '')
        ;
    }
}

//************************************************************
//
//           YOU MAY NOT NEED TO MODIFY FOLLOWING CODE 
//
//************************************************************


/*to do:
html interface
pass GET parameters
body of php
*/

$current = array(
    'isCacheOpened' => false,
    'isDBOpened' => false,
    );
$db = [];
$cache = [];//cache has exact same structure, but only cache less records

function chkString($str) {
    global $db,$cache,$config,$current;
        
    $result = true;
    
    for ($i=0;$i<strlen($str);$i++) {
        $found = false;
        $strTmp = substr($str,$i,1);
        for ($j=0;$j<strlen($config['base']);$j++) {
            $Basetmp = substr($config['base'],$j,1);
            if ($Basetmp == $strTmp) {
                $found = true;
                break;
            }
        } 
        if (!$found) {
            $result = false;
            break;
        }   
    }
    return $result;
}

function initDB() {
    global $db,$cache,$config,$current;
    if (!$current['isDBOpened']) {
        if (!(file_exists($config['dbPath']))) {
            file_put_contents($config['dbPath'], '{}');
        }
        $dbRaw = file_get_contents($config['dbPath'], true);
        $db = json_decode($dbRaw,true);
        $current['isDBOpened'] = true;
    }
}

function initCache() {
    global $db,$cache,$config,$current;
    if (!$current['isCacheOpened']) {
        if (!(file_exists($config['cachePath']))) {
            file_put_contents($config['cachePath'], '{}');
        }
        $cacheRaw = file_get_contents($config['cachePath'], true);
        $cache = json_decode($cacheRaw,true);
        $current['isCacheOpened'] = true;
    }
}

function refreshCache() {
    global $db,$cache,$config,$current;
    if ($current['isCacheOpened']) {
        while (count($cache) > $config['cacheMax']) {
            array_shift($cache);
        }
    }
}

function processGetParameters($go,$get,$path,$type) {   
    global $db,$cache,$config,$current;
    return $config['urlType'][$type]($go,$get,$path,$path);
}

function get($go) {
    global $db,$cache,$config,$current;
    initCache();
    $preResult = ''; //cache found URL and validate it later, improve performance by store it without search again in the array
    $result = false;
    $type = 'default';
    if (isset($cache[$go])) {
        $preResult = $cache[$go]['url'];
        $type = $cache[$go]['type'];
    } else {
        initDB();
        if (isset($db[$go])) {
            $preResult = $db[$go]['url'];
            $type = $cache[$go]['type'];
        }
    }
    if (filter_var($preResult, FILTER_VALIDATE_URL) != false) {
        $cache[$go]['url'] = $preResult;
        $cache[$go]['type'] = $type;
        write('cache');
        
        $result['url'] = $preResult;
        if (isset($_GET['get'])) {
            $result['url'] = processGetParameters($preResult,$_GET['get'],$_GET['path'],$type);
        }
    }
    
    return $result;
}

function gen() {
    global $db,$cache,$config,$current;
    $result = '';
    $t = $config['base']; 
    $l = strlen($t);    
    $length = $config['length'];
    for ($i=0;$i<$length;$i++) {
        $r = mt_rand(0,$l-1);
        $c = $t[$r];
        $result .= $c;
    }
    return $result;
}

function write($type) {
    global $db,$cache,$config,$current;
    if (($type == 'db') && ($current['isDBOpened'])){
        file_put_contents($config['dbPath'], json_encode($db));
    } elseif (($type == 'cache') && ($current['isCacheOpened'])) {
        refreshCache();
        file_put_contents($config['cachePath'], json_encode($cache));
    }
}

function add($url,$type=NULL,$name='') {
    global $db,$cache,$config,$current;
    $result = '';
    if ($type == NULL) $type = 'default';
    if (filter_var($url, FILTER_VALIDATE_URL) != false) {
        initDB();
        $g = '';
        if (empty($name)) {
            
            do {
                $g = gen();
            } while (isset($db[$g]));
        } else {
            if ((!isset($db[$name])) && (chkString($name))) {
                $g = $name;
            }
        }
        if (!empty($g)) {
            $db[$g]['url'] = $url;
            $db[$g]['type'] = $type;
            write('db');
            if ($config['writeToCacheAfterAdd']) {
                initCache();
                $cache[$g]['url'] = $url;
                $cache[$g]['type'] = $type;
                write('cache');
            }
            $result = $config['siteRoot'] . $g;
        } else {
            $result = 'FAILED!';        
        }
    }
    return $result;
}

function html() {
    global $db,$cache,$config,$current;
?>
    <html>

    <head>
        <title>URL Shortener</title>
        <style>
            body {
                margin: 0;
                padding: 0;
                background: #D5EAE7;
                font-family: sans-serif;
            }
            
            #headContainerDiv {
                text-align: center;
                width: 100%;
                height: 100px;
                margin: 0;
                padding: 0;
            }
            
            #headDiv {
                display: inline-block;
                position: relative;
                width: 1100px;
                font-size: 80px;
                line-height: 100px;
                vertical-align: middle;
                text-align: left;
                margin: 0;
                padding: 0;
                border: 0;
            }
            
            #headLogoDiv {
                display: inline-block;
                font-size: 80px;
                font-family: monospace;
                font-weight: bold;
                margin: 0;
                padding: 0;
                border: 0;
            }
            
            #headSiteLogoDiv {
                display: inline-block;
                color: #D5EAE7;
                background: #AAA;
                line-height: 1em;
                font-size: 38px;
                vertical-align: middle;
                height: 42px;
                margin-bottom: 6px;
                font-family: monospace;
                font-weight: bold;
            }
            
            #contentContainerDiv {
                height: 80%;
                text-align: center;
                width: 100%;
                min-height: 400px;
                margin: 0;
                padding: 0;
                background: #52DDD9;
            }
            
            #contentDiv {
                display: inline-block;
                position: relative;
                width: 1100px;
                height: 100%;
            }
            
            .vCenterWrap {
                display: flex;
                justify-content: center;
                height: 100%;
            }
            
            #formContainerDiv {
                color: #022D3D;
                font-size: 24px;
                align-self: center;
            }
            
            #creditContainerDiv {
                text-align: center;
                width: 100%;
                height:10%;
            }
            
            #creditDiv {
                font-family: monospace;
                font-weight: bold;
                font-size: 18px;
                display: inline-block;
                position: relative;
                width: 1100px;
                height: 100%;
                color: #022D3D;
            }
            
            input[type='button'] {
                border: #D5EAE7 1px solid;
                background: #F2F5A9;
                font-size: 24px;
                color: #0979A5;
            }
            
            input[type='url'],
            input[type='text'],
            input[type='password'],
            select {
                border: 0;
                color: #0979A5;
                font-size: 24px;
                background: #F2F5A9;
            }
        </style>
        <script>
            function shorten() {
                //Simple AJAX
                var inputURL = document.getElementById('url').value;
                var inputName = document.getElementById('name').value;
                var inputKey = document.getElementById('key').value;
                var inputType = document.getElementById('type').options[document.getElementById('type').selectedIndex].text;
                if (window.XMLHttpRequest) {
                    // code for IE7+, Firefox, Chrome, Opera, Safari
                    xmlhttp = new XMLHttpRequest();
                } else {
                    // code for IE6, IE5
                    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                }

                xmlhttp.onreadystatechange = function () {
                    if (xmlhttp.readyState == XMLHttpRequest.DONE) {
                        if (xmlhttp.status == 200) {
                            var respone = JSON.parse(xmlhttp.responseText);
                            document.getElementById("shortenUrl").value = respone.shortenURL;
                        } else if (xmlhttp.status == 400) {
                            alert('There was an error 400')
                        } else {
                            alert('something else other than 200 was returned')
                        }
                    }
                }
                xmlhttp.open("GET", "/?action=add&url=" + encodeURIComponent(inputURL) + '&key=' + inputKey + '&name=' + inputName + '&type=' + encodeURIComponent(inputType), true);
                xmlhttp.send();
            }
        </script>
    </head>

    <body>
        <div id="headContainerDiv">
        <div id="headDiv">
            <div id="headLogoDiv">SHORTENER</div>
            <div id="headSiteLogoDiv">by Luniz</div>
        </div>
    </div>
    <div id="contentContainerDiv">
        <div id="contentDiv">
            <div class="vCenterWrap">
                <div id="formContainerDiv">
                    <form>
                        <label>URL:</label>
                        <input id="url" type="url" name="url" size="90" />
                        <br/>
                        <br/> Key:
                        <input id="key" type="password" name="key" size="16" /> Type:
                        <select id="type" style="min-width:250px;">
                            <?php foreach ($config['urlType'] as $key => $value) { echo "<option>$key</option>"; } ?>
                        </select>
                        <label>Custom:&nbsp;<?php echo $config['siteRoot']; ?></label>
                        <input id="name" type="text" name="name" size="10" />/


                        <br/>
                        <br/>
                        <div style="text-align:center;">
                            <input type="button" value="Shorten!" onclick="javascript:shorten();" />
                        </div>
                        <br/>
                        <br/>
                        <label>Result:</label>
                        <input id="shortenUrl" type="text" name="shortenUrl" size="90" value="" />
                        <br/>
                </div>
                </form>
            </div>
        </div>
    </div>
    <div id="creditContainerDiv">
        <div id="creditDiv">
            <br/> &copy; Wilson Luniz @ Previous Pd. Macau
        </div>
    </div>
                
    </body>

    </html>
    <?php
}
//body of the php
if (isset($_GET['go'])) {
    $go = get($_GET['go']);
    if ($go != false) {
        //Success
        
        http_response_code(301);
        header('Location: ' . $go['url']);
    } else {
        http_response_code(301);
        header('Location: /');
    }
} else {
    if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case "add":
            if ((isset($_GET['url'])) && (!empty($_GET['url']))) {
                if (((!empty($config['key'])) && ($config['key'] == $_GET['key'])) || (empty($config['key']))) {
                    $type = 'default';
                    if (isset($_GET['type'])) $type = $_GET['type'];
                    if (isset($_GET['name'])) {
                        $name = $_GET['name'];
                    } else {
                        $name = '';
                    }
                    $result = add($_GET['url'],$type,$name);
                    header("Content-Type: application/json");
                    echo "{ \"shortenURL\": \"$result\" }";
                }
            }
            break;
        default:
            html();
    } 
} else {
        html();
    }
}
        
?>
