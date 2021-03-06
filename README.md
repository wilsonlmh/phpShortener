# phpShortener
A URL install-and-go shorten-er without database server. Using JSON as store suitable for small instance with fast installation.

##Requirement/Installation:
  - Any webserver with PHP 5+
  - Custom anything you need in the $config selection
  - Config rewrite rule in your HTTP server
  - There's all

###Rewrite Rules:
 - RewriteRule ^index\/(.*)$ index.php{R:1}
 - RewriteRule ^\?(.*)$  index?{R:1}  //? is a reserved word for URL, DON'T use it for shorten base text
 - RewriteCond {QUERY_STRING}=^(.*)$ 
 - RewriteRule ^([0-9+a-z+A-z+\.\!\*\(\)]+)\/?(.*)$  index.php?go={R:1}&get={UrlEncode:{C:0}}&path={UrlEncode:{R:2}}

##What you get?
###A web UI interface to shorten URL
Just go to http(s)://your_server/

###Access API via:
http(s)://your_server/?action=add&url=`{urlencoded_original_url}`&key=`{customable_password}`&name=`{customable_short_Name}`&type=`{customable_redirect_rule}`

result JSON respone:  $JSONObject->shortenURL


####Follow parameters can be omited:
 - `{customable_password}`
 - `{customable_short_Name}`
 - `{customable_redirect_rule}` //default value will become 'default'

##Integrate with `shorten` ownCloud plugin
You can use the 'shorten' ownCloud plugin to shorten the share url. 
The plugin is here: https://github.com/wilsonlmh/shorten
The configuration is:
  - customURL = //The API URL above//
  - customJSON = '->shortenURL'

##Security Concern:
If you use rewrite rules above, any file except index.php won't be accessed by users. So you don't need to concern the json file(you can also change the path in $config) expose to users. If you need something special, I recommend you apply a segment filter(or something similar) in your server config(e.g. .htaccess).
