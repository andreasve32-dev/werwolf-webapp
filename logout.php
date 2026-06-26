<?php
// Copyright (c) 2026 Andreas Vetter
require_once __DIR__ . '/core/bootstrap.php';
Auth::logout();
redirect('/index.php');
