The Flow Platform: PHP Client Library
========================================

Usage
-----

    include &#39;flow.php&#39;;

    $api = new Flow(array(
      &#39;key&#39;     => $YOUR_APP_KEY,
      &#39;secret&#39;  => $YOUR_APP_SECRET
    ));

    $api->set_global_actor($ID_OF_IDENTITY_TO_DO_BUSINESS_AS);

Examples
--------

### Documented Examples

1. Turn type hinting off

    <pre>
    $api->set_global_params(array(
      &#39;hints&#39; => 0
    ));
    </pre>

2. Retrieve a flow by its ID

    <pre>
    $api->get("/bucket/$ID");
    </pre>

3. Retrieve a flow by its path

    <pre>
    $opts = array(
      &#39;params&#39; => array(
        &#39;criteria&#39; => sprintf(&#39;{"path": "%s"}&#39;, $PATH)
      )
    );

    $api->get(&#39;/bucket&#39;, $opts);
    </pre>

4. Retrieve the drops from a flow

    <pre>
    $opts = array(
      &#39;params&#39; => array(
        &#39;start&#39; => $OFFSET,
        &#39;limit&#39; => $LIMIT
      )
    );

    $api->get("/drop/$BUCKET_ID", $opts);
    </pre>

5. Retrive **all** the drops from a flow

    <pre>
    function get_drops($api, $bucket_id, $offset, $limit) {
      $opts = array(
        &#39;params&#39; => array(
          &#39;start&#39; => $offset,
          &#39;limit&#39; => $limit
        )
      );

      $results = json_decode($api->get("/drop/$bucket_id", $opts), TRUE);

      return isset($results[&#39;head&#39;])
        && isset($results[&#39;body&#39;])
        && isset($results[&#39;head&#39;][&#39;ok&#39;])
        && sizeof($results[&#39;body&#39;]) > 0
        ? $results[&#39;body&#39;]
        : array();
    }

    $offset = 0;
    $limit = 3;
    $drops = array();

    do { 
      $more = get_drops($api, $bucket->body[0]->id, $offset, $limit);
      $drops = array_merge($drops, $more);
      $offset += $limit;
    } while(sizeof($more) > 0);
    </pre>

6. Create a drop

    <pre>
    $data = sprintf(&#39;
    { "path" : "%s"
    , "elems" :
      { "title" : { "type" : "string", "value" : "%s" }
      , "description" : { "type" : "string", "value" : "%s" }
      }
    }&#39;, $PATH, $TITLE, $DESCRIPTION);

    $api->post(&#39;/drop&#39;, $data);
    </pre>

7. Delete a drop

    <pre>
    $api->delete("/drop/$BUCKET_ID/$ID");
    </pre>

### Executable Examples

<pre>
<?php

include &#39;flow.php&#39;;

$api = new Flow(array(
  &#39;key&#39;     => $argv[1],
  &#39;secret&#39;  => $argv[2]
));

$api->set_global_actor($argv[3]);

$api->set_global_params(array(
  &#39;hints&#39; => 0
));

$api->get("/bucket/$argv[3]"), "\n";

$opts = array(
  &#39;params&#39; => array(
    &#39;criteria&#39; => sprintf(&#39;{"path": "%s"}&#39;, "/apps/fmk/control")
  )
);

$bucket = json_decode($api->get(&#39;/bucket&#39;, $opts));

$opts = array(
  &#39;params&#39; => array(
    &#39;start&#39; => 4,
    &#39;limit&#39; => 6
  )
);

$drops = $api->get(&#39;/drop/&#39; . $bucket->body[0]->id, $opts);

function get_drops($api, $bucket_id, $offset, $limit) {
  $opts = array(
    &#39;params&#39; => array(
      &#39;start&#39; => $offset,
      &#39;limit&#39; => $limit
    )
  );

  $results = json_decode($api->get("/drop/$bucket_id", $opts), TRUE);

  return isset($results[&#39;head&#39;])
    && isset($results[&#39;body&#39;])
    && isset($results[&#39;head&#39;][&#39;ok&#39;])
    && sizeof($results[&#39;body&#39;]) > 0
    ? $results[&#39;body&#39;]
    : array();
}

$offset = 0;
$limit = 3;
$all_drops = array();

do { 
  $more = get_drops($api, $bucket->body[0]->id, $offset, $limit);
  $all_drops = array_merge($all_drops, $more);
  $offset += $limit;
} while(sizeof($more) > 0);

$data = sprintf(&#39;
{ "path" : "%s"
, "elems" :
  { "title" : { "type" : "string", "value" : "%s" }
  , "description" : { "type" : "string", "value" : "%s" }
  }
}&#39;, &#39;/system&#39;, &#39;title&#39;, &#39;description&#39;);

$drop = json_decode($api->post(&#39;/drop&#39;, $data));
$api->delete(&#39;/drop/&#39; . $drop->body->bucketId . &#39;/&#39; . $drop->body->id));
</pre>

Author / Maintainer
-------------------

Jeffrey Olchovy <jeff@flow.net>
