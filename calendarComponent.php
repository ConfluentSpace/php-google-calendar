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

$time = time();
$optParams = array(
  'maxResults' => 100,
  'orderBy' => 'startTime',
  'singleEvents' => TRUE,
  'timeMin' => date('c', $time-1800/*30min*/ ),
//  'timeMax' => date('c', time()+31536000/*year*/),
  'timeMax' => date('c', $time+5270400/*~2 months (61 days)*/),
);
$events = $service->events->listEvents(CALENDAR, $optParams)->getItems();
?>
<template id="template">
  <style>
    .cancelled {
        background: red;
    }
    
    .event {
      margin-bottom: 1.25em;
      
      display: flex;
      flex-direction: row;
      
      color: black;
    }

    .event h4 {
      margin-top: 0;
    }
    
    .event .summary {
      margin-bottom: .3125em;
    }
    
    .event .summary a {
      color: #D0722C;
    }
    
    .event .register {
      float: right;
      font-size: 1.25em;
      padding: .3em 1em;
      background: #f99d33;
      color: #1e4897;
      text-decoration: none;
      border-radius: 5px;
    }
    
    .event .when {
      font-size: small;
      font-weight: bold;
      margin-bottom: 1em;
      color: #555
    }
    
    .event .shortdesc, .event .description {
      white-space: pre-wrap;
      padding: 0 .3125em 0 0;
    }

    .event .description > p:first-child,
    .event .shortdesk > p:first-child {
      margin-top: 0;
    }

    .event .description > p:last-child {
      margin-bottom: 0;
    }

    .event .details {
      display: none;
    }
    
    .event .location {
      float: right;
    }

    .event .preview {
      float: right;
    }

    .event .preview img {
      max-width: 200px;
    }
    
    .event .price {
      margin-bottom: 1em;
      font-size: small;
      font-weight: bold;
      padding-right: 5px;
      white-space: pre-wrap;
    }
    
    .event-content {
      flex: 1 1 auto;
      position: relative;
      overflow: hidden;
      background: white;
      padding: .625em;
      border-radius: 5px;
    }
    
    .calendar-date {
      align-self: flex-start;
      flex: 0 0 auto;
      
      box-sizing: border-box;
      background-color:#FFFFFF;
      border-radius: 5px;

      padding-bottom: .625em;
      width: 80px;
      overflow: hidden;
      
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
      line-height:100%;
      padding: .625em 0;
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

    .event-content .more {
      border-radius: .5em;
      display: block;
      background: #f99d33;
      height: 1em;
      line-height: 1em;
      width: 2em;
      text-align: center;
      font-weight: bold;
      color: #1e4897;
      cursor: pointer;
      margin: 1em auto;
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
    $storeLink = preg_split('/">/', $matches[0], -1, PREG_SPLIT_NO_EMPTY)[0];
    
    preg_match('/((Free)|(\$\S+)) [^\x0a\x0d<>]* Members[^\x0a\x0d<>]* ((Free)|(\$\S+)) for non-members/i', $event->description, $matches);
    $price = $matches[0];
    $price = str_replace("</p><p>","\n",$price);
    
    
    $hasdesc = strlen($event->description) > 0;
    $hasmore = FALSE;

    $descshort = preg_split('/\n|\r/', $event->description, -1, PREG_SPLIT_NO_EMPTY);
    $descshort = $descshort[0];
    $descshort = substr($descshort, 0, 200);
    if( strlen($descshort) != strlen($event->description) ) {
      if ( preg_match('/<[[:alpha:]]+[^>]*$/', $descshort) ) {
        $descshort = preg_replace("/<[[:alpha:]]+[^>]*$/","",$descshort);
      } else if ( preg_match('/<[[:alpha:]]>[^<]*(?:<\/[[:alpha:]]+)?$/', $descshort) ) {
        $descshort = preg_replace("/<([[:alpha:]]+)( ?[[:alpha:]]?+)>([^<]+)(?:<\/[[:alpha:]]+)?$/","<$1$2>$3</$1>",$descshort);
      }
      $descshort = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~",'<a href="$0">$0</a>',$descshort);
      $descshort .= "…";
      $hasmore = TRUE;
    } else {
      $descshort = preg_replace("~[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]~",'<a href="$0">$0</a>',$descshort);
    }

    $img = "";
    $attachments = $event->getAttachments();
    if( $attachments && count($attachments) > 0 ) {
      foreach ( $attachments as $attachment ) {
          $img = "https://drive.google.com/uc?id=".$attachment->fileId;
        break;
      }
    }
    
    $cssClass =
      ($isNew?"new":"") +
      ($isUpdated?"updated":"") +
      ($isCancelled?"cancelled":"") +
      "";
?>

<div class="event <?=$cssClass?>">
  <div class="calendar-date">
    <div class="month"><?=(new DateTime($start))->format("M")?></div>
    <div class="date"><?=(new DateTime($start))->format("d")?></div>
    <div class="day"><?=(new DateTime($start))->format("D")?></div>
  </div>
  <div class="event-content">
    <?php if ($storeLink) { ?><a class="register" href="<?=$storeLink?>">Register</a><?php } ?>
    <h4 class="summary"><a href="<?=$event->htmlLink?>"><?=$event->summary?></a></h4>
    <div class="when"><?=(new DateTime($start))->format("g:i a")?> - <?=(new DateTime($end))->format("g:i a")?></div>
    <div class="price"><?=$price?></div>
    <?php if($hasdesc) { ?>
    <div class="shortdesc"><?=$descshort?></div>
    <?php } ?>
    <?php if($event->location || $hasmore) { ?>
    <span class="more" title="Click to show more details" onClick="if (event.target.previousElementSibling.style.display) { event.target.previousElementSibling.style.display = ''; event.target.nextElementSibling.style.display = ''; } else { event.target.previousElementSibling.style.display = 'none'; event.target.nextElementSibling.style.display = 'block'; }">&#8943;</span>
    <div class="details">
      <?php if($img) { ?>
      <div class="preview"><img alt="<?=$event->summary.' preview image'?>" src="<?=$img?>"></div>
      <?php } ?>
      <div class="description"><?=$event->description?></div>
    </div>
    <?php } ?>
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
