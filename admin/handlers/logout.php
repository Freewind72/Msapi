<?php

pusher_trigger(PUSHER_CHANNEL, 'user-offline', ['user_id' => (string)$_SESSION['admin_id'], 'username' => $_SESSION['admin_user']]);
session_destroy();
header('Location: ?');
exit;