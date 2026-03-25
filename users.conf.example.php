<?php
// NOTE: dùng công cụ https://bcrypt-generator.com/ để tạo hash cho mật khẩu
$users = array(
    'demo' => array(
        'name' => 'demo',
        'password_hash' => '$2a$12$sP3UoUx.3zJPPQlsTvMHuuyTuyo8GqL1D2hixFM14IfuGuUJPPXaa',
        'acl' => ['editor', 'diff', 'snr'],
        'plugins' => ['editor', 'diff', 'snr'],
        'repositories' => ['_']
    ),
    // Thêm user khác vào đây
);

return $users;
