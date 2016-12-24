<?php

// workaround for the amazingly annoying magic quotes.
function magicQuotesSuck(&$data)
{
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                magicQuotesSuck($data[$k]);
                continue;
            }
            $data[$k] = stripslashes($v);
        }
    }
}
