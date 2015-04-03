<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf8">
    <title><?php echo $pageTitle ?></title>
    <style>
      body {
        font-family: Calibri, Arial, sans-serif;
      }
      table {
        border-collapse: collapse;
        width: 100%
      }
      .issues {
        font-size: .6em;
        line-height: 2em
      }
      tr.highlight td.highlight {
        background: #ccc;
      }

      td.user, td.count {
        white-space: pre
      }
      td.count {
        text-align: right
      }
      th {
        text-align: left;
        background: #eee;
        white-space: pre;
        position: relative;
      }
      td.highlight {
        width: 1.2em;
      }
      th[data-sort] {
        cursor: pointer;
        padding-right: 1em;
      }
      th[data-sort]:hover {
        background: #ccc;
      }
      th.sorting-asc:after,
      th.sorting-desc:after {
        position: absolute;
        right: .2em;
        font-size: .7em;
        top: .5em;
      }
      th.sorting-asc:after {
        content: "▲"
      }
      th.sorting-desc:after {
        content: "▼"
      }
      th, td {
        vertical-align: top;
        padding: 3px;
      }
      th, td {
        border: 1px solid #ddd
      }
      a {
        text-decoration: none;
        color: black;
      }
      .issues a {
        border-radius: 10px;
        padding: 1px 4px;
        background: #ddd
      }
      .issues a:visited {
        color: #aaa;
        background: #eee
      }
      .debug {
        white-space: pre;
        font-size: .7em;
        background: #eee;
        padding: 10px;
      }
    </style>
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
    <script src="http://rawgithub.com/joequery/Stupid-Table-Plugin/master/stupidtable.min.js"></script>
    <script type="text/javascript">
      $(document).ready(function() {
        $('table').stupidtable();
      });
    </script>
  </head>
  <body>
    <div class="content"><?php echo $output ?></div>
    <div class="debug"><?php echo $debugInfo ?></div>
  </body>
</html>