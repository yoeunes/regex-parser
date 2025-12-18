<?php

preg_replace_callback_array([
    "/pattern1/" => "callback1",
    "/pattern2/" => "callback2",
], $data);