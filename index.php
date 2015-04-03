<?php
error_reporting(E_ALL);
ini_set('display_errors','On');

require_once 'lib/phpQuery-onefile.php';

class DOIssues
{
  protected $issuesUrl;

  protected $fetchDelay = 1;

  protected $filterDefaults = array(
    'status'     => 'All',
    'priorities' => 'All',
    'categories' => 'All',
    'version'    => 'All',
    'component'  => 'All',
  );

  public $cacheFile;

  protected $lastRequest = 0.0;


  public function __construct($url, $cacheId = null) {
    $this->issuesUrl = $url;
    $cacheId = is_null($cacheId) ? sha1($url) . '_' . date('YmdHis') : $cacheId;
    $this->cacheFile = "cache/$cacheId.cache";
    if(!$this->cacheGet('date', false))
    {
      $this->cacheSet('date', time());
    }
  }


  protected function log($msg)
  {
    echo "\n" . htmlentities($msg) . "\n";
  }


  public function cacheGet($key = null, $default = null)
  {
    $data = @json_decode(@file_get_contents($this->cacheFile), true);
    if(is_null($key))
    {
      return is_array($data) ? $data : array();
    }
    if((is_array($data) && array_key_exists($key, $data)))
    {
      return $data[$key];
    }
    return $default;
  }


  public function cacheSet($key, $value)
  {
    $data = $this->cacheGet();
    $data[$key] = $value;
    file_put_contents($this->cacheFile, json_encode($data));
  }


  protected function fetchContent($url)
  {
    $wait = $this->lastRequest + $this->fetchDelay - microtime(true);
    if($wait > 0) {
      $this->log('Waiting ' . (number_format($wait, 4)) . ' s ...');
      usleep(round($wait * 1000));
    }

    $this->lastRequest = microtime(true);
    $this->log('Fetching ' . $url . ' ...');
    return @file_get_contents($url);
  }


  public function getIssues($filters = array())
  {
    $issueIds = $this->cacheGet('issueIds', array());
    if($issueIds)
    {
      return $issueIds;
    }

    $filters += $this->filterDefaults;
    $currentPage = 1;
    $pageCount = 1;

    while($currentPage <= $pageCount)
    {
      $filters['page'] = $currentPage - 1;
      $url = $this->issuesUrl . '?' . http_build_query($filters);
      $html = $this->fetchContent($url, 1);

      if(!$html)
      {
        $this->log('Page ' . $url . ' not found - aborting.');
        break;
      }

      $view = phpQuery::newDocumentHTML($html)->find('div.view-project-issue-project-searchapi');

      // Update page count on first request to avoid querying the first page twice
      if($currentPage === 1)
      {
        $lastPageHref = $view->find('li.pager-last a')->attr('href');
        $pageCount = $lastPageHref ? end(explode('page=', $lastPageHref)) + 1 : 1;
      }

      foreach($view->find('table.project-issue tbody tr') as $tr)
      {
        $tr = pq($tr);
        $a = $tr->find('.views-field-title a');
        $href = $a->attr('href');
        $nid = end(explode('/', $href));
        $issueIds[$nid] = array(
          'title' => trim($a->text()),
          'comments' => (int) $tr->find('.views-field-comment-count')->text()
        );
      }

      $currentPage++;
    }

    $this->cacheSet('issueIds', $issueIds);

    return $issueIds;
  }


  public function getUsers($issueIds)
  {
    $baseUrl = 'https://drupal.org/node/';
    $data = array(
      'users'  => $this->cacheGet('users', array()),
      'issues' => $this->cacheGet('issues', array()),
      'failed' => $this->cacheGet('failed', array())
    );

    foreach($issueIds as $nid => $issueData)
    {
      if(isset($data['issues'][$nid]) || isset($data['failed'][$nid]))
      {
        continue;
      }

      $url = $baseUrl . $nid;
      $html = $this->fetchContent($url);
      if(!$html)
      {
        $data['failed'][$nid] = $url;
        $this->cacheSet('failed', $data['failed']);
        continue;
      }
      $userLinks = phpQuery::newDocumentHTML($html)->find('.submitted a.username');
      foreach($userLinks as $a)
      {
        $a = pq($a);
        $name = $a->text();
        $uid = $a->attr('data-uid');

        $data['users'][$uid]['name'] = $name;
        $data['users'][$uid]['issues'][$nid] = $nid;
        $data['issues'][$nid][$uid] = $uid;
      }

      $this->cacheSet('users', $data['users']);
      $this->cacheSet('issues', $data['issues']);
    }

    return $data;
  }


  public function renderSummary($users, $issueIds, $project = null, $highlight = array())
  {
    // @todo fix awful naming (issueIds <> issues)

    $counts = array();
    $names = array();
    $blacklist = array('180064' => 'System Message');

    foreach($users as $uid => $user)
    {
      $counts[$uid] = count($user['issues']);
      $names[$uid] = $user['name'];
    }

    $uids = array_flip($names);

    asort($names);
    arsort($counts);

    $colors = array();
    $highlightedUsers = array();
    foreach($highlight as $highlightedUser => $color)
    {
      $uid = isset($uids[$highlightedUser]) ? $uids[$highlightedUser] : $highlightedUser;
      if(isset($names[$uid]))
      {
        $colors[$color] = $color;
        $highlightedUsers[$uid] = $color;
      }
    }
    // Get some numerical values for ordering
    $colors = array_flip(array_keys(array_reverse($colors)));

    $title = !is_null($project) ? $project : $this->issuesUrl;

    $output = '';
    $output .= '<h3>' . htmlentities($title) . '</h3>';

    $rowTemplate = ''
      . '<tr class="{rowClass}">'
      . '<td class="highlight" data-sort-value="{colorIndex}" style="background-color:{highlight}"></td>'
      . '<td class="user">{user}</td>'
      . '<td class="count">{count}</td>'
      . '<td class="issues">{issues}</td>'
      . '</tr>';

    $rows = array();
    foreach($names as $uid => $name)
    {
      if(isset($blacklist[$uid]))
      {
        continue;
      }
      $highlight = isset($highlightedUsers[$uid]) ? $highlightedUsers[$uid] : null;
      $userLink = sprintf('<a href="https://drupal.org/user/%s">%s</a>', $uid, htmlentities($name));

      $issues = $users[$uid]['issues'];
      sort($issues);
      $issues = array_flip($issues);
      foreach(array_keys($issues) as $nid)
      {
        $issues[$nid] = sprintf('<a title="%s" href="https://drupal.org/node/%d">%d</a>', htmlentities($issueIds[$nid]['title']), $nid, $nid);
      }

      $data = array(
        '{rowClass}'  => !is_null($highlight) ? 'highlight' : '',
        '{highlight}' => htmlentities($highlight),
        '{colorIndex}' => !is_null($highlight) ? $colors[$highlight] : -1,
        '{user}'      => $userLink,
        '{count}'     => $counts[$uid],
        '{issues}'    => implode(' ', $issues)
      );

      $rows[] = strtr($rowTemplate, $data);
    }
    $output .= '<table>'
      . '<thead><tr>'
      . '<th data-sort="int" data-sort-default="desc">!</th>'
      . '<th data-sort="string">User</th>'
      . '<th data-sort="int" data-sort-default="desc">Count</th>'
      . '<th>Issues</th>'
      . '</tr></thead>';
    $output .= '<tbody>' . implode(' ', $rows) . '</tbody>';
    $output .= '</table>';
    return $output;
  }
}

if(empty($_GET['project']))
{
  exit('No project name provided.');
}

$project = $_GET['project'];

ob_start();
$doi      = new DOIssues('https://drupal.org/project/issues/' . $project, $project);
$issueIds = $doi->getIssues();
$data     = $doi->getUsers($issueIds);

// highlight.ini contains a list of user names and uids that should be highlighted
$highlight = parse_ini_file('highlight.ini');

$output    = $doi->renderSummary($data['users'], $issueIds, $project, $highlight);
$debugInfo = htmlentities(ob_get_clean());

$pageTitle = htmlentities($project);

require 'tpl/html.tpl.php';