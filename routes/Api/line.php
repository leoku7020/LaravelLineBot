<?php

Route::group(['prefix' => 'v1/line'], function () {
    Route::post('callBack', 'LineBotController@callBack');
});