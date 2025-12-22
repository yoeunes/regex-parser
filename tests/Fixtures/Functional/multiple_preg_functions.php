<?php
// Multiple preg functions - ALL should be detected
preg_match('/pattern1/', (string) $str);
preg_replace('/pattern2/', 'replacement', (string) $text);
preg_split('/pattern3/', (string) $data);
