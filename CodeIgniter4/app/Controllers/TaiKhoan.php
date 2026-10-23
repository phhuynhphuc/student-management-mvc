<?php

namespace App\Controllers;

use App\Models\ThanhVienModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use Throwable;

class TaiKhoan extends BaseController
{
    private const UPLOAD_PATH = WRITEPATH . 'uploads';

    public function register()
    {
        return view('taikhoan/register', [
            'errors' => session()->getFlashdata('errors') ?? [],
        ]);
    }

    public function store()
    {
        $rules = [
            'username' => 'required|alpha_numeric_punct|max_length[50]|is_unique[thanhvien.username]',
            'hoten' => 'required|regex_match[/\S/]|max_length[100]',
            'matkhau' => 'required|min_length[6]|max_length[255]',
            'gioitinh' => 'permit_empty|in_list[Nam,Nữ]',
            'quocgia' => 'permit_empty|max_length[50]',
            'hinhdaidien' => 'permit_empty|is_image[hinhdaidien]|max_size[hinhdaidien,2048]|mime_in[hinhdaidien,image/jpeg,image/png,image/gif,image/webp]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $avatar = $this->saveAvatar($this->request->getFile('hinhdaidien'));
        if ($avatar === false) {
            return redirect()->back()->withInput()->with('errors', ['hinhdaidien' => 'Không thể lưu ảnh đại diện.']);
        }

        $model = new ThanhVienModel();
        try {
            if (! $model->insert([
                'username' => trim((string) $this->request->getPost('username')),
                'matkhau' => password_hash((string) $this->request->getPost('matkhau'), PASSWORD_DEFAULT),
                'hoten' => trim((string) $this->request->getPost('hoten')),
                'gioitinh' => $this->request->getPost('gioitinh') ?: null,
                'quocgia' => trim((string) $this->request->getPost('quocgia')) ?: null,
                'hinhdaidien' => $avatar,
            ])) {
                $this->removeAvatar($avatar);
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
        } catch (Throwable $exception) {
            $this->removeAvatar($avatar);
            log_message('error', 'Could not register member: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('errors', ['general' => 'Không thể tạo tài khoản. Vui lòng thử lại.']);
        }

        return redirect()->to(site_url('dang-nhap'))->with('success', 'Đăng ký thành công. Bạn có thể đăng nhập.');
    }

    public function login()
    {
        if (session('member')) {
            return redirect()->to(site_url('tai-khoan'));
        }

        $errors = session()->getFlashdata('errors') ?? [];
        $error = session()->getFlashdata('error');
        if ($this->request->is('post')) {
            if (! $this->validate([
                'username' => 'required|max_length[50]',
                'matkhau' => 'required|max_length[255]',
            ])) {
                return view('taikhoan/login', ['errors' => $this->validator->getErrors(), 'error' => null]);
            }

            $member = (new ThanhVienModel())->findForLogin(trim((string) $this->request->getPost('username')));
            if ($member && password_verify((string) $this->request->getPost('matkhau'), $member['matkhau'])) {
                session()->regenerate(true);
                session()->set('member', ['username' => $member['username']]);

                return redirect()->to(site_url('tai-khoan'))->with('success', 'Đăng nhập thành công.');
            }

            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }

        return view('taikhoan/login', compact('errors', 'error'));
    }

    public function logout()
    {
        session()->remove('member');
        session()->regenerate(true);

        return redirect()->to(site_url('dang-nhap'))->with('success', 'Bạn đã đăng xuất.');
    }

    public function profile()
    {
        $model = new ThanhVienModel();
        $member = $model->find(session('member')['username']);
        if (! $member) {
            session()->remove('member');
            return redirect()->to(site_url('dang-nhap'))->with('error', 'Không tìm thấy tài khoản. Vui lòng đăng nhập lại.');
        }

        unset($member['matkhau']);

        return view('taikhoan/profile', [
            'member' => $member,
            'errors' => session()->getFlashdata('errors') ?? [],
        ]);
    }

    public function update()
    {
        $username = session('member')['username'];
        $model = new ThanhVienModel();
        $member = $model->find($username);
        if (! $member) {
            return redirect()->to(site_url('dang-nhap'))->with('error', 'Không tìm thấy tài khoản.');
        }

        $rules = [
            'hoten' => 'required|regex_match[/\S/]|max_length[100]',
            'matkhau' => 'permit_empty|min_length[6]|max_length[255]',
            'gioitinh' => 'permit_empty|in_list[Nam,Nữ]',
            'quocgia' => 'permit_empty|max_length[50]',
            'hinhdaidien' => 'permit_empty|is_image[hinhdaidien]|max_size[hinhdaidien,2048]|mime_in[hinhdaidien,image/jpeg,image/png,image/gif,image/webp]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $avatar = $this->saveAvatar($this->request->getFile('hinhdaidien'));
        if ($avatar === false) {
            return redirect()->back()->withInput()->with('errors', ['hinhdaidien' => 'Không thể lưu ảnh đại diện.']);
        }

        $data = [
            'hoten' => trim((string) $this->request->getPost('hoten')),
            'gioitinh' => $this->request->getPost('gioitinh') ?: null,
            'quocgia' => trim((string) $this->request->getPost('quocgia')) ?: null,
        ];
        $password = (string) $this->request->getPost('matkhau');
        if ($password !== '') {
            $data['matkhau'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($avatar !== null) {
            $data['hinhdaidien'] = $avatar;
        }

        try {
            if (! $model->update($username, $data)) {
                $this->removeAvatar($avatar);
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
        } catch (Throwable $exception) {
            $this->removeAvatar($avatar);
            log_message('error', 'Could not update member profile: {message}', ['message' => $exception->getMessage()]);
            return redirect()->back()->withInput()->with('errors', ['general' => 'Không thể cập nhật hồ sơ.']);
        }

        if ($avatar !== null) {
            $this->removeAvatar($member['hinhdaidien']);
        }

        return redirect()->to(site_url('tai-khoan'))->with('success', 'Đã cập nhật hồ sơ.');
    }

    public function avatar(string $filename)
    {
        if (basename($filename) !== $filename || ! preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
            return $this->response->setStatusCode(404);
        }

        $path = self::UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        if (! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        $mime = mime_content_type($path);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->setHeader('Content-Type', $mime)
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody(file_get_contents($path));
    }

    private function saveAvatar(?UploadedFile $file): string|null|false
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid() || $file->hasMoved()) {
            return false;
        }

        if (! is_dir(self::UPLOAD_PATH) && ! mkdir(self::UPLOAD_PATH, 0750, true) && ! is_dir(self::UPLOAD_PATH)) {
            return false;
        }

        $name = $file->getRandomName();
        return $file->move(self::UPLOAD_PATH, $name) ? $name : false;
    }

    private function removeAvatar(?string $filename): void
    {
        if (! $filename || basename($filename) !== $filename) {
            return;
        }

        $path = self::UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
