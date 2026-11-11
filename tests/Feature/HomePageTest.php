<?php

it('відкриває головну сторінку', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('site.tagline'));
});

it('відкриває сторінку входу в адмін-панель', function () {
    $this->get('/admin/login')->assertOk();
});
