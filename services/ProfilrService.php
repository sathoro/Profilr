<?php

/**
 *
 * @package     Craft Profilr
 * @version     Version 1.0
 * @author      Connor Smith
 * @copyright   Copyright (c) 2013
 * @link        sphinx.io
 *
 */

namespace Craft;

class ProfilrService extends BaseApplicationComponent
{
    public function __construct()
    {

    }

    public function alert($opts)
    {
        if (!isset($opts['time']) || !is_numeric($opts['time']))
        {
            throw new Exception(Craft::t('Time must be set and must be a number.'));
        }

        if (isset($opts['email']))
        {
            $email = $opts['email'];

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            {
                throw new Exception(Craft::t('Specified email is not valid.'));
            }
        }
        else 
        {
            //$email = craft()->systemSettings->getSettings('email')['emailAddress'];

            if (!is_null($email))
            {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
                {
                    throw new Exception(Craft::t('Email in configuration is not valid. (' . $email . ')'));
                }
            }
            else 
            {
                throw new Exception(Craft::t('Email needs to be specified either in tag parameter or in configuration.'));
            }
        }
        
        $time = Craft::getLogger()->getExecutionTime();

        if ($time > $opts['time'])
        {
            $emailModel = new EmailModel();

            $emailModel->toEmail  = $email;
            $emailModel->subject  = "Craft Profilr Alert!";
            $emailModel->body     = var_dump($_SERVER, $_POST, $_GET, $time);

            $html = print_r(craft()->email->sendEmail($emailModel), true);
        }
        else
        {
            $html = "";
        }

        $charset = craft()->templates->getTwig()->getCharset();
        
        return new \Twig_Markup($html, $charset);
    }

    public function display($opts)
    {
        $opts = array_merge(
            array(
                'debug_backtrace' => false,
                'sort' => true,
                'admin' => false,
                'devMode' => false
            ), $opts
        );

        if ($_SERVER['HTTP_USER_AGENT'] === 'ZCache') return "";
        if ($opts['admin'] && !craft()->userSession->isAdmin()) return "";
        if ($opts['devMode'] && !craft()->config->get('devMode')) return "";

        $total_runtime = Craft::getLogger()->getExecutionTime();

        $html = "<pre class=\"profilr_layer\" style=\"display:inline-block;padding-top:15px\"><b>Total page execution time:</b> " . $this->formatMicrotime($total_runtime) . "<br />";
        $html .= "<b>Total page memory usage:</b> " . $this->formatBytes(Craft::getLogger()->getMemoryUsage()) . "<p>";

        $db_stats = craft()->db->getStats();

        $html .= "<b>Database execution time:</b> " . $this->formatMicrotime($db_stats[1])  . "<br />";
        $html .= "<b>Database queries:</b> " . $db_stats[0] . " queries<br />";
        $html .= "<b>Percentage of total run-time:</b> " . sprintf('%0.2f', $db_stats[1] / $total_runtime * 100) . "%";

        if ($opts['debug_backtrace']) $html .= "</pre><div class=\"show_profilr\">Debug Backtrace</div><pre class=\"profilr_layer\">" . print_r(debug_backtrace(), true) . "</pre>";
        
        $html .= "</pre><div class=\"show_profilr\">Show {$db_stats[0]} Queries</div><pre class=\"profilr_layer\">";

        $queries = Craft::getLogger()->getProfilingResults();

        if ($opts['sort'])
        {
            $html .= "<p>Sorted <b>descending</b> by <b>total execution time</b></p>";
            usort($queries, function($a, $b) {
                return $a[2] < $b[2];
            });
        }

        foreach ($queries as $query)
        {
            $html .= $this->formatQuery($query[0]);
            $html .= "<b>Time</b>: " . $this->formatMicrotime($query[2]);
            $html .= "<p>";
        }

        $html .= "</pre><div class=\"show_profilr\">POST Data</div><pre class=\"profilr_layer\">" . print_r($_POST, true) . "</pre>";
        $html .= "<div class=\"show_profilr\">GET Data</div><pre class=\"profilr_layer\">" . print_r($_GET, true) . "</pre>";
        $html .= "<div class=\"show_profilr\">SESSION Data</div><pre class=\"profilr_layer\">" . print_r($_SESSION, true) . "</pre>";
        $html .= "<div class=\"show_profilr\">COOKIE Data</div><pre class=\"profilr_layer\">" . print_r($_COOKIE, true) . "</pre>";

        $this->prepareCSS();
        $this->prepareJS();

        $charset = craft()->templates->getTwig()->getCharset();
        
        return new \Twig_Markup($html, $charset);
    }

    private function formatQuery($query)
    {
        $query = ltrim($query, 'end:system.db.CDbCommand.query(');
        $query = rtrim($query, ')');
        return "<textarea class=\"profilr_highlight\" name=\"profilr_highlight\">" . $query . "</textarea>";
    }

    private function formatMicroTime($time)
    {
        return sprintf('%0.4f', $time) . " seconds";
    }

    private function formatBytes($size, $precision = 2)
    {
        $base = log($size) / log(1024);
        $suffixes = array('', 'kb', 'MB', 'Gb', 'Tb');   

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    private function prepareCSS()
    {
        $css = <<<CSS
        .profilr_highlight {display: none;}

        .profilr_layer {
            font-family: monospace;
            font-size: 13px;
            line-height: 20px;
            border: 1px solid #ccc;
            background: #efefef;
            padding: 3px 30px;
            white-space: pre-wrap;
            word-wrap: break-word;
            display:none;
        }

        .show_profilr {
            padding: 10px;
            width: 200px;
            background: #222;
            color: #f6f6f6;
            font-family: verdana;
            font-size: 16px;
            margin: 11px;
            cursor: pointer;
            margin-left: 0;
        }

        .profilr_sc {color: brown;}
        .profilr_keyword {color: purple; font-weight: bold;}
        .profilr_string {color: darkgreen;}
        .profilr_comment {color: green; background: lightyellow;}
        .profilr_function {color: red;}
CSS;

        craft()->templates->includeCss($css);
    }

    private function prepareJS()
    {
        $js = <<<JAVASCRIPT
        if (typeof jQuery == 'undefined') {
            document.write('<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"><\/script>');
        }

        var si;
        si = setInterval(function() {
            if (typeof jQuery === "function") {
                  clearInterval(si);
                  profilrInit();
            }
        }, 50);
        
        function profilrInit() {
            var k = ["AND", "AS", "ASC", "BETWEEN", "BY", "CASE", "CURRENT_DATE", "CURRENT_TIME", "DELETE", "DESC", "DISTINCT", "EACH", "ELSE", "ELSEIF", "FALSE", "FOR", "FROM", "GROUP", "HAVING", "IF", "IN", "INSERT", "INTERVAL", "INTO", "IS", "JOIN", "KEY", "KEYS", "LEFT", "LIKE", "LIMIT", "MATCH", "NOT", "NULL", "ON", "OPTION", "OR", "ORDER", "OUT", "OUTER", "REPLACE", "RIGHT", "SELECT", "SET", "TABLE", "THEN", "TO", "TRUE", "UPDATE", "VALUES", "WHEN", "WHERE"];

            var len = k.length;
            for(var i = 0; i < len; i++)
            {
                k.push(k[i].toLowerCase());
            }
            
            var re, c;
            $(".profilr_highlight").each(function() {
                c = $(this).val();

                c = c.replace(/(=|%|\/|\*|-|,|;|\+|<|>)/g, "<span class=\"profilr_sc\">$1</span>");
                c = c.replace(/(['`].*?['`])/g, "<span class=\"profilr_string\">$1</span>");
                c = c.replace(/(\\d+)/g, "<span class=\"profilr_string\">$1</span>");
                c = c.replace(/(\\w*?)\(/g, "<span class=\"profilr_function\">$1</span>(");
                c = c.replace(/([\(\)])/g, "<span class=\"profilr_sc\">$1</span>");

                for(var i = 0; i < k.length; i++)
                {
                    re = new RegExp("\\\b"+k[i]+"\\\b", "g");
                    c = c.replace(re, "<span class=\"profilr_keyword\">"+k[i]+"</span>");
                }
                
                c = c.replace(/(#.*?\\n)/g, clear_spans);
                c = c.replace(/<span class=\"profilr_sc\">-<\/span><span class=\"profilr_sc\">-<\/span>/g, "--");
                c = c.replace(/(-- .*?\\n)/g, clear_spans);
                c = c.replace(/<span class=\"profilr_sc\">\/<\/span><span class=\"profilr_sc\">\*<\/span>/g, "/*");
                c = c.replace(/<span class=\"profilr_sc\">\*<\/span><span class=\"profilr_sc\">\/<\/span>/g, "*/");
                c = c.replace(/(\/\*[\s\S]*?\*\/)/g, clear_spans);
                
                $(this).after('<pre class="profilr_layer">' + c + '</pre>');
            });
            
            function clear_spans(match)
            {
                match = match.replace(/<span.*?>/g, "");
                match = match.replace(/<\/span>/g, "");
                return "<span class=\"profilr_comment\">"+match+"</span>";
            }

            $('body').on('click', '.show_profilr[data-js!=open]', function() {
                $(this).attr('data-js', 'open').next().show().find('.profilr_layer').show();
            });

            $('body').on('click', '.show_profilr[data-js=open]', function() {
                $(this).attr('data-js', 'closed').next().hide().find('.profilr_layer').hide();
            });
        }
JAVASCRIPT;

        craft()->templates->includeJs($js);
    }
}