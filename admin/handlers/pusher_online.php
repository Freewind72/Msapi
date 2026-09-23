<?php

header('Content-Type: application/json');
echo json_encode(pusher_online_users());
exit;