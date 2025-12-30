<?php
$subject = 'bar';
preg_match('/foo/i', $subject);
preg_replace_callback_array(['#bar#' => 'cb'], $subject);