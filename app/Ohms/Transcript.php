<?php namespace Ohms;

class Transcript
{
    private $transcript;
    private $chunks;
    private $transcriptHTML;
    private $index;
    private $indexHTML;

    public function __construct($transcript, $timecodes, $index, $translate = false)
    {
        $this->transcript = (string)$transcript;
        $this->index = $index;
        $this->chunks = $timecodes;
        $this->formatTranscript();
        $this->formatIndex($translate);
    }

    public function getTranscriptHTML()
    {
        if (isset($this->transcriptHTML)) {
            return $this->transcriptHTML;
        }
    }

    public function getTranscript()
    {
        if (isset($this->transcript)) {
            return $this->transcript;
        }
    }

    public function getIndexHTML()
    {
        if (isset($this->indexHTML)) {
            return $this->indexHTML;
        }
    }

    private function formatIndex($translate)
    {
        if (!empty($this->index)) {
            if (count($this->index->point) == 0) {
                $this->indexHTML = '';
                return;
            }
            $indexHTML = "<div id=\"accordionHolder\">\n";
            foreach ($this->index->point as $point) {
                $timePoint = $this->formatTimepoint($point->time);
                $synopsis = $translate ? $point->synopsis_alt : $point->synopsis;
                $partial_transcript = $translate ? $point->partial_transcript_alt : $point->partial_transcript;
                $keywords = $translate ? $point->keywords_alt : $point->keywords;
                $subjects = $translate ? $point->subjects_alt : $point->subjects;
                $gps = $point->gps;
                $zoom = (empty($point->gps_zoom) ? '17' : $point->gps_zoom);
                $gps_text = $translate ? $point->gps_text_alt : $point->gps_text;
                $hyperlink = $point->hyperlink;
                $hyperlink_text = $translate ? $point->hyperlink_text_alt : $point->hyperlink_text;

                $title = $translate ? $point->title_alt : $point->title;
                $formattedTitle = trim($title, ';');
                $protocol = 'https';
                if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
                    $protocol = 'http';
                }
                $host = $_SERVER['HTTP_HOST'];
                $uri = $_SERVER['REQUEST_URI'];
                $directSegmentLink = "$protocol://$host$uri#segment{$point->time}";
                $nlPartialTranscript = nl2br($partial_transcript);
                $nlSynopsis = nl2br($synopsis);
                $formattedKeywords = str_replace(';', '; ', $keywords);
                $formattedSubjects = str_replace(';', '; ', $subjects);
                $gpsHTML = '';
                $indexText = "";
                if(!empty($nlPartialTranscript) && trim($nlPartialTranscript) != ""){
                    $indexText .= '<p><strong>Partial Transcript:</strong> <span>'.$nlPartialTranscript.'</span></p>';
                }
                if(!empty($nlSynopsis) && trim($nlSynopsis) != ""){
                    $indexText .= '<p><strong>Segment Synopsis:</strong> <span>'.$nlSynopsis.'</span></p>';
                }
                if(!empty($formattedKeywords) && trim($formattedKeywords) != ""){
                    $indexText .= '<p><strong>Keywords:</strong> <span>'.$formattedKeywords.'</span></p>';
                }
                if(!empty($formattedSubjects) && trim($formattedSubjects) != ""){
                    $indexText .= '<p><strong>Subjects:</strong> <span>'.$formattedSubjects.'</span></p>';
                }
                if ($gps <> '') {
                    # XXX: http
                    $mapUrl = htmlentities(
                        str_replace(
                            ' ',
                            '',
                            'http://maps.google.com/maps?ll='.$gps.'&t=m&z=' . $zoom . '&output=embed'
                        )
                    );
                    $gpsHTML = '<br/><strong>GPS:</strong> <a    class="fancybox-media" href="' . $mapUrl . '">';
                    if ($gps_text <> '') {
                        $gpsHTML .= $gps_text;
                    } else {
                        $gpsHTML .= 'Link to map';
                    }
                    $gpsHTML .= '</a><br/><strong>Map Coordinates:</strong> ' . $gps .'<br/>';

                }
                $hyperlinkHTML = '';
                if ($hyperlink <> '') {
                    $hyperlinkHTML = <<<HYPERLINK
<br/>
<strong>Hyperlink:</strong>
<a class="fancybox" rel="group" target="_new" href="{$hyperlink}">{$hyperlink_text}</a><br/>
HYPERLINK;
                }
                $indexHTML .= <<<POINT
<h3><a href="#" id="link{$point->time}">{$timePoint} - {$formattedTitle}</a></h3>
<div class="point">
  <p>
    <a class="indexJumpLink" href="#" data-timestamp="{$point->time}">Play segment</a>
    <a class="indexSegmentLink" href="#" data-timestamp="{$point->time}">Segment link</a>
    <br clear="both" />
  </p>
  <div class="segmentLink" id="segmentLink{$point->time}" style="width:100%">
    <strong>Direct segment link:</strong>
    <br />
    <a href="{$directSegmentLink}">{$directSegmentLink}</a>
  </div>
  <div class="synopsis"><a name="tp_{$point->time}"></a>
    {$indexText}
    {$gpsHTML}
    {$hyperlinkHTML}
  </div>
</div>
POINT;

            }
            $this->indexHTML = $indexHTML . "</div>\n";
        }
    }

    private function formatTranscript()
    {
        $this->transcriptHTML = $this->transcript;
        if (strlen($this->transcriptHTML) == 0) {
            return;
        }

        # quotes
        $this->transcriptHTML = preg_replace('/\"/', "&quot;", $this->transcriptHTML);

        # paragraphs
        $this->transcriptHTML = preg_replace('/Transcript: */', "", $this->transcriptHTML);

        # highlight kw

        # take timestamps out of running text
        $this->transcriptHTML = preg_replace("/{[0-9:]*}/", "", $this->transcriptHTML);

        $this->transcriptHTML = preg_replace('/(.*)\n/msU', "<p>$1</p>\n", $this->transcriptHTML);

        # grab speakers
        $this->transcriptHTML = preg_replace(
            '/<p>[[:space:]]*([A-Z-.\' ]+:)(.*)<\/p>/',
            "<p><span class=\"speaker\">$1</span>$2</p>",
            $this->transcriptHTML
        );

        $this->transcriptHTML = preg_replace('/<p>[[:space:]]*<\/p>/', "", $this->transcriptHTML);

        $this->transcriptHTML = preg_replace('/<\/p>\n<p>/ms', "\n", $this->transcriptHTML);

        $this->transcriptHTML = preg_replace('/<p>(.+)/U', "<p class=\"first-p\">$1", $this->transcriptHTML, 1);

        $chunkarray = explode(":", $this->chunks);
        $chunksize = (int)$chunkarray[0];
        $chunklines =array();
        if (count($chunkarray)>1) {
            $chunkarray[1] = preg_replace('/\(.*?\)/', "", $chunkarray[1]);
            $chunklines = explode("|", $chunkarray[1]);
        }
        (empty($chunklines[0])) ? $chunklines[0] = 0 : array_unshift($chunklines, 0);

        # insert ALL anchors
        $itlines = explode("\n", $this->transcriptHTML);
        foreach ($chunklines as $key => $chunkline) {
            $stamp = $key*$chunksize . ":00";
            $anchor = <<<ANCHOR
<a href="#" data-timestamp="{$key}" data-chunksize="{$chunksize}" class="jumpLink">{$stamp}</a>
ANCHOR;
            $itlines[$chunkline] = $anchor . $itlines[$chunkline];
        }

        $this->transcriptHTML = "";
        $noteNum = 0;
        $supNum = 0;
        foreach ($itlines as $key => $line) {
            if (strstr($line, '[[footnote]]') !== false) {
                $line = preg_replace(
                    '/\[\[footnote\]\]([0-9]+)\[\[\/footnote\]\]/',
                    '<a name="sup' . ++$supNum . '"></a><a href="#footnote$1" class="footnoteLink">[$1]</a>',
                    $line
                );
            }
            $line = str_replace('[[footnotes]]', '', $line);
            $line = str_replace('[[/footnotes]]', '', $line);
            $matches = array();
            preg_match('/\[\[link\]\](.*)\[\[\/link\]\]/', $line, $matches);
            if (isset($matches[1])) {
                $footnoteLink = $matches[1];
                $line = preg_replace('/\[\[link\]\](.*)\[\[\/link\]\]/', '', $line);
                $noteNum += 1;
                $prefix = <<<FOOTNOTE
<a name="footnote$noteNum"></a>
<div>
  <a class="footnoteLink" href="#sup$noteNum">$noteNum</a>. <a class="footnoteLink" href="$footnoteLink" target="_new">
FOOTNOTE;
                $line = str_replace('[[note]]', $prefix, $line);
                $line = str_replace('[[/note]]', '</a></div>', $line);
            } else {
                if (strstr($line, '[[note]]') !== false) {
                    $noteNum += 1;
                    $prefix = <<<FOOTNOTE
<a name="footnote$noteNum"></a>
<div>
  <a class="footnoteLink" href="#sup$noteNum">$noteNum</a>.
FOOTNOTE;
                    $line = str_replace('[[note]]', $prefix, $line);
                    $line = str_replace('[[/note]]', '</div>', $line);
                }
            }
            $this->transcriptHTML .= "<span class='transcript-line' id='line_$key'>$line</span>\n";
        }
    }

    private function formatShortline($line, $keyword)
    {
        $shortline = preg_replace("/.*?\s*(\S*\s*)($keyword.*)/i", "$1$2", $line);
        $shortline = preg_replace("/($keyword.{30,}?).*/i", "$1", $shortline);
        $shortline = preg_replace("/($keyword.*\S)\s+\S*$/i", "$1", $shortline);
        $shortline = preg_replace("/($keyword)/mis", "<span class='highlight'>$1</span>", $shortline);
        $shortline = preg_replace('/\"/', "&quot;", $shortline);

        return $shortline;
    }

    private function quoteWords($string)
    {
        $q_kw = preg_replace('/\'/', '\\\'', $string);
        $q_kw = preg_replace('/\"/', "&quot;", $q_kw);
        return $q_kw;
    }

    private function quoteChange($string)
    {
        $q_kw = preg_replace('/\'/', "&#39;", $string);
        $q_kw = preg_replace('/\"/', "&quot;", $string);
        $q_kw = trim($q_kw);
        return $q_kw;
    }

    public function keywordSearch($keyword)
    {
        # quote kw for later
        $q_kw = $this->quoteWords($keyword);
        $json = "{ \"keyword\":\"$q_kw\", \"matches\":[";

        //Actual search
        $lines = explode("\n", $this->transcript);
        $totalLines = sizeof($lines);
        foreach ($lines as $lineNum => $line) {
            if (preg_match("/$keyword/i", $line, $matches)) {
                if ($lineNum < $totalLines-1) {
                    $line .= ' ' . $lines[$lineNum + 1];
                }
                $shortline = $this->formatShortline($line, $keyword);
                if (strstr($json, 'shortline')) {
                    $json .= ',';
                }
                $json .= "{ \"shortline\" : \"$shortline\", \"linenum\": $lineNum }";
            }
        }

        return str_replace("\0", "", $json) . ']}';
    }

    public function indexSearch($keyword, $translate)
    {
        if (!empty($keyword)) {
            $q_kw = $this->quoteWords($keyword);
            $metadata = array(
                'keyword' => $q_kw,
                'matches' => array(),
            );

            foreach ($this->index->point as $point) {
                $synopsis = $translate ? $point->synopsis_alt : $point->synopsis;
                $keywords = $translate ? $point->keywords_alt : $point->keywords;
                $subjects = $translate ? $point->subjects_alt : $point->subjects;
                $time = $point->time;
                $title = $translate ? $point->title_alt : $point->title;
                $timePoint = floor($time / 60) . ':' . str_pad($time % 60, 2, '0', STR_PAD_LEFT);
                $gps = $point->gps;
                $hyperlink = $point->hyperlink;

                if (preg_match("/{$keyword}/imsU", $synopsis) > 0
                || preg_match("/{$keyword}/ismU", $title) > 0
                || preg_match("/{$keyword}/ismU", $keywords) > 0
                || preg_match("/{$keyword}/ismU", $subjects) > 0
                || preg_match("/{$keyword}/ismu", $gps) > 0
                || preg_match("/{$keyword}/ismu", $hyperlink) > 0) {
                    $metadata['matches'][] = array(
                        'time' => (string)$time,
                        'shortline' => $timePoint . ' - ' . $this->quoteChange($title),
                    );
                }
            }
        }

        return json_encode($metadata);
    }

    private function formatTimePoint($timePoint)
    {
        $minutes = floor((int)$timePoint / 60);
        $seconds = (int)$timePoint % 60;
        return sprintf("%d:%02d", $minutes, $seconds);
    }
}
