<?PHP
/**
 *  +--------------------------------------------------------------
 *  | Copyright (c) 2016 DZLZH All rights reserved.
 *  +--------------------------------------------------------------
 *  | Author: DZLZH <dzlzh@null.net>
 *  +--------------------------------------------------------------
 *  | Filename: courses.php
 *  +--------------------------------------------------------------
 *  | Last modified: 2016-11-15 20:55
 *  +--------------------------------------------------------------
 *  | Description: 
 *  +--------------------------------------------------------------
 */

require_once 'config.php';

if (!file_exists('cookie')) {
    $login = curl_html($url['login'], $userAgent, null, null, null, 1);
    $cookieIsMatched = preg_match('/Set-Cookie:\s*(.*)/', $login, $matches);
    if ($cookieIsMatched) {
        $cookie = strstr($matches[1], ';', true);
    }

    $csrfTokenIsMatched = preg_match('/csrf_token.*value="([^"]*)"/', $login, $matches);
    if ($csrfTokenIsMatched) {
        $loginParam['csrf_token'] = $matches[1];
    }

    $login = curl_html($url['login'], $userAgent, $cookie, $loginParam, null, 1);
    $cookieIsMatched = preg_match('/Set-Cookie:\s*(.*)/', $login, $matches);
    if ($cookieIsMatched) {
        $cookie .= ';' . strstr($matches[1], ';', true);
    }
    file_put_contents('cookie', $cookie) || exit;
}

$cookie = file_get_contents('cookie');
if ($cookie) {
    //循环类型
    foreach ($tag as $key => $value) {
        // echo $value, "\n";
        //目录
        $coursesParam['tag'] = $value;
        $coursesTagUrl = $url['courses'];
        foreach ($coursesParam as $k => $v) {
            //当前类型URL
            $coursesTagUrl .= '&' . $k . '=' . urlencode($v);
        }
        $page = 1;
        $coursesUrl = array();
        //当前类型全部课程URL
        while(true) {
            $tagUrl = $coursesTagUrl . '&page=' . $page;
            // echo $tagUrl , "\n";
            $tagHtml = curl_html($tagUrl, $userAgent, $cookie);
            $isMatched = preg_match_all('/<a\s*class="course-box"\s*href="([^"]+)">/i', $tagHtml, $matches);
            if ($isMatched) {
                $coursesUrl = array_merge_recursive($coursesUrl, $matches[1]);
            } else {
                break;
            }
            $page++;
        }
 
        foreach ($coursesUrl as $courseUrl) {
            // echo $courseUrl, "\n";
            $courseHtml = curl_html($url['root'] . $courseUrl, $userAgent, $cookie);
            //课程名字
            if ($coursesParam['fee'] == 'limited') {
                $isMatched = preg_match('/course-infobox-type[^<]*<\/span>\s*<span>([^<]*)/i', $courseHtml, $matches);
            } else {
                $isMatched = preg_match('/<h4\s*class="pull-left[^>]*>\s*<span>([^<]+)<\/span>\s*<\/h4>/i', $courseHtml, $matches);
            }
            if ($isMatched) {
                $courseName = $matches[1];
                $directory[$value][] = $courseName;
            }
            // echo $courseName, "\n";
            // die;
            //课程内容URL
            $isMatched = preg_match_all('/\/courses\/\d+\/labs\/\d+\/document/i', $courseHtml, $matches);
            if ($isMatched) {
                //课程内容
                foreach ($matches[0] as $k => $documentUrl) {
                    // echo $documentUrl, "\n";
                    $documentHtml = curl_html($url['root'] . $documentUrl, $userAgent, $cookie);
                    //小节名字
                    $isMatched = preg_match('/<li\s*class="active">([^<]*)<\/li>/i', $documentHtml, $matches);
                    $documentName = $isMatched ? $k . '.' .trim($matches[1], " \t\n\r\0\x0B") : $k;
                    // echo $documentName;
                    //课程Markdown
                    $path = $basePath . str_replace('/', '&', trim($value)) . DIRECTORY_SEPARATOR . str_replace('/', '&', trim($courseName)) . DIRECTORY_SEPARATOR;
                    $fileName = $path . str_replace('/', '&', trim($documentName)) . '.md';
                    if (!file_exists($fileName)) {
                        $isMatched = preg_match('/<textarea[^>]*>([^<]*)<\/textarea>/i', $documentHtml, $matches);
                        if ($isMatched) {
                            $document = $matches[1];
                            if (mk_dir($path)) {
                                file_put_contents($fileName, $document) || exit;
                            }
                        }
                    } else {
                        echo $fileName, "  --  exists\n";
                    }
                    // echo $document, "\n";
                }
            }
        }
    }
    foreach ($directory as $key => $value) {
        echo '[', $key, '](', $basePath, str_replace('/', '&', trim($key)), ")\n"; 
        if (is_array($value)) {
            foreach ($value as $v) {
                echo '- [', $v, '](', $basePath,  str_replace('/', '&', trim($key)), DIRECTORY_SEPARATOR,  str_replace('/', '&', trim($v)), ")\n"; 
            }
        }
        echo "\n";
    }
}
