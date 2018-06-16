<?php
header("Access-Control-Allow-Origin: *");

require __DIR__ . '/vendor/autoload.php';

require __DIR__ . '/.config.php';

define('APPLICATION_NAME', $APPLICATION_NAME);
define('CLIENT_SECRET_PATH', __DIR__ . '/.google-secret.json');
define('SCOPES', Google_Service_Calendar::CALENDAR_READONLY);
define('CALENDAR', $CALENDAR);


/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
  $client = new Google_Client();
  $client->setApplicationName(APPLICATION_NAME);
  $client->setScopes(SCOPES);
  $client->setAuthConfig(CLIENT_SECRET_PATH);
  $client->setAccessType('offline');
  return $client;
}


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Calendar($client);

$optParams = array(
  'maxResults' => 100,
  'orderBy' => 'startTime',
  'singleEvents' => TRUE,
  'timeMin' => date('c', time()-1800/*30min*/ ),
  'timeMax' => date('c', time()+31536000/*year*/),
);
$events = $service->events->listEvents(CALENDAR, $optParams)->getItems();
?>
<template id="template">
  <style>
    .cancelled {
        background: red;
    }
    
    .event {
      font-family: sans-serif;
      margin-top: 10px;
      background: #ddd;
      
      display: flex;
      flex-direction: row;
      
      color: black;
    }
    
    .event:nth-child(odd) {
      background: #eee;
    }
    
    .event .summary {
      margin: 0;
      padding: 5px 0 0 0;
    }
    
    .event .summary a {
      color: #D0722C;
    }
    
    .event .register {
      float: right;
      padding: 10px;
      background: #f99d33;
      color: black;
      font-weight: bold;
    }
    
    .event .when {
      font-size: small;
      font-weight: bold;
      margin-bottom: 1em;
      color: #555
    }
    
    .event .description {
      white-space: pre-wrap;
      padding: 0 5px 0 0;
      display: table;
    }
    
    .event .location {
      float: right;
    }
    
    .event .price {
      position: absolute;
      right: 0;
      bottom: 0;
      
      font-size: small;
      font-weight: bold;
      padding-right: 5px;
      text-align: right;
      white-space: pre-wrap;
    }
    
    .event-content {
      flex: 1 1 auto;
      position: relative;
    }
    
    .calendar-date {
      flex: 0 0 auto;
      
      box-sizing: border-box;
      background-color:#FFFFFF;
      border:1px solid #CCCCCC;
      /*margin: 0 auto;*/
      padding: 5px;
      width: 120px;
      
      color:#D0722C;
      font-family:Helvetica, Arial, sans-serif;
      font-weight:bold;
      text-align:center;
      
      line-height: 0;
      
      margin-right: 10px;
    }
    
    .calendar-date .month {
      background-color:#f99d33;
      color:#FFFFFF;
      font-size:16px;
      line-height:100%;
      padding-top:10px;
      padding-bottom:10px;
    }
    
    .calendar-date .date {
      font-size:40px;
      line-height:100%;
      padding-top:15px;
      padding-bottom:10px;
    }
    
    .calendar-date .day {
      font-size:15px;
      line-height:100%;
      padding-top:0px;
      padding-bottom:0px;
    }
  </style>
  <div>
<?php
if (count($events) == 0) {
  print "No upcoming events found.\n";
} else {
  foreach ($events as $event) {
    $start = $event->start->dateTime;
    if (empty($start)) {
      $start = $event->start->date;
    }
    $end = $event->end->dateTime;
    if (empty($end)) {
      $end = $event->end->date;
    }
    
    $isNew = (new DateTime())->sub(new DateInterval("P1D")) < new DateTime($event->created);
    $isUpdated = (new DateTime())->sub(new DateInterval("P1D")) < new DateTime($event->updated);
    $isCancelled = $event->status == "cancelled";
    
    preg_match('/https:\/\/store.confluent.space\/([^\s<])*/', $event->description, $matches);
    $storeLink = $matches[0];
    
    preg_match('/((Free)|(\$\S+)) [^\x0a\x0d<>]* Members[^\x0a\x0d<>]* ((Free)|(\$\S+)) for non-members/i', $event->description, $matches);
    $price = $matches[0];
    $price = str_replace("</p><p>","\n",$price);
    
    
    
    $descshort = preg_split('/\n|\r/', $event->description, -1, PREG_SPLIT_NO_EMPTY);
    $descshort = $descshort[0];
    $descshort = substr($descshort, 0, 200);
    if( strlen($descshort) != strlen($event->description) ) {
      
    $descshort = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~",'<a href="$0">$0</a>',$descshort);
      $descshort .= "...";
    } else {
      $descshort = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~",'<a href="$0">$0</a>',$descshort);
    }
    
    $clazz =
      ($isNew?"new":"") +
      ($isUpdated?"updated":"") +
      ($isCancelled?"cancelled":"") +
      "";
      
    
?>

<div class="event <?=$clazz?>">
  <div class="calendar-date">
		<div class="month"><?=(new DateTime($start))->format("F")?></div>
		<div class="date"><?=(new DateTime($start))->format("d")?></div>
		<div class="day"><?=(new DateTime($start))->format("D")?></div>
	</div>
	<div class="event-content">
  	<?php if($event->location) { ?>
      <div class="location"><img title="<?=$event->location?>" src="https://maps.googleapis.com/maps/api/staticmap?style=feature:poi|element:labels.text|visibility:off&markers=color:blue%7C<?=urlencode($event->location)?>&zoom=17&size=200x100&key=<?=$MAPS_BROWSER_KEY ?>" /></div>
    <?php } ?>
  	<?=$storeLink?"":"<!--"?><a class="register" href="<?=$storeLink?>">Register</a><?=$storeLink?"":"-->"?>
  	<h4 class="summary"><a href="<?=$event->htmlLink?>"><?=$event->summary?></a></h4>
    
    <div class="when"><?=(new DateTime($start))->format("g:i a")?> - <?=(new DateTime($end))->format("g:i a")?></div>
  	<div class="description"><?=$descshort?></div>
    <div class="price"><?=$price?></div>
    <div style="clear:both;"></div>
  </div>
</div>

<?php
  }
}
?>
  </div>
</template>
<script>
  (function (doc) {
        document.registerElement('confluent-calendar', {
            prototype: Object.create(HTMLElement.prototype, {
                createdCallback: {
                    value: function() {
                        var template = doc.querySelector('#template');
                        var clone = document.importNode(template.content, true);
                        this.appendChild(clone);
                    }
                }
            })
        });
  })(document._currentScript.ownerDocument); // pass document of component
</script>