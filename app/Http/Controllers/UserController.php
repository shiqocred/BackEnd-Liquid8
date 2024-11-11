<?php

namespace App\Http\Controllers;

use App\Http\Resources\ResponseResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q');

        $users = User::where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('name', 'LIKE', '%' . $query . '%')
                ->orWhere('username', 'LIKE', '%' . $query . '%');
        })->with('role')->latest()->paginate(50);

        return new ResponseResource(true, "List users", $users);
    }

    public function show(Request $request, User $user)
    {
        if ($user) {
            return new ResponseResource(true, "detail user ", $user);
        } else {
            return new ResponseResource(false, "data user tidak ada", []);
        }
    }

    public function store(Request $request)
    {}

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2',
            'username' => 'required|min:2|unique:users,username,' . $id,
            'email' => 'required|min:2|unique:users,email,' . $id,
            'password' => 'nullable',
            'role_id' => 'required|exists:roles,id',
        ], [
            'username.unique' => 'Username sudah ada',
            'email.unique' => 'Email sudah ada',
            'role_id.exists' => 'Role tidak ada',
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $user = User::find($id);
        if (!$user) {
            return new ResponseResource(false, "User not found", null);
        }

        $dataToUpdate = $request->only(['name', 'username', 'email', 'role_id']);

        if ($request->filled('password')) {
            $dataToUpdate['password'] = $request->input('password');
        }

        $user->update($dataToUpdate);

        return new ResponseResource(true, "User updated successfully", $user);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return new ResponseResource(false, "User not found", null);
        }

        $user->delete();

        return new ResponseResource(true, "User deleted successfully", null);
    }

    public function exportUsers()
    {
        // Meningkatkan batas waktu eksekusi dan memori
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $userHeaders = [
            'id',
            'name',
            'username',
            'email',
            'role_id',
            'role_name',
        ];

        $columnIndex = 1;
        foreach ($userHeaders as $header) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $header);
            $columnIndex++;
        }

        $rowIndex = 2; // Mulai dari baris kedua

        $users = User::with('role')->get();
        foreach ($users as $user) {
            $columnIndex = 1;

            // Menuliskan data user ke sheet
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->id);
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->name);
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->username);
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->email);
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->role_id);
            $sheet->setCellValueByColumnAndRow($columnIndex++, $rowIndex, $user->role ? $user->role->role_name : '');

            $rowIndex++;
        }

        // Menyimpan file Excel
        $writer = new Xlsx($spreadsheet);
        $fileName = 'users_export.xlsx';
        $publicPath = 'exports';
        $filePath = public_path($publicPath) . '/' . $fileName;

        if (!file_exists(public_path($publicPath))) {
            mkdir(public_path($publicPath), 0777, true);
        }

        $writer->save($filePath);

        $downloadUrl = url($publicPath . '/' . $fileName);

        return new ResponseResource(true, "unduh", $downloadUrl);
    }

    public function generateApiKey($userId)
    {
        $user = User::find($userId);

        if ($user) {
            $apiKey = $user->generateApiKey();

            return new ResponseResource(true, "generate api_key", $apiKey);
        }

        return response()->json(['message' => 'User not found'], 404);
    }

    public function checkLogin(Request $request)
    {
        $user = auth()->user();
        if (!empty($user)) {
            $userData = [
                'username' => $user->username,
                'email' => $user->email,
                'role_name' => $user->role->role_name,
            ];

            return new ResponseResource(true, "login", $userData);
        } else {
            $response = new ResponseResource(false, "user tidak valid", null);
            return $response->response()->setStatusCode(401);
        }
    }

    public function addFormatBarcode(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format_barcode' => 'required|string',
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json(["errors" => $validator->errors()], 422);
            }

            $formatBarcode = $request->input('format_barcode');
            $user_id = $request->input('user_id');

            $user = User::find($user_id);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $user->update([
                'format_barcode' => $formatBarcode,
            ]);

            return new ResponseResource(true, "Berhasil menambahkan format barcode", $user->format_barcode);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function deleteFormatBarcode($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        $user->update([
            'format_barcode' => null,
        ]);
        return new ResponseResource(true, "Berhasil menambahkan format barcode", []);
    }

    public function showFormatBarcode($id)
    {
        $user = User::find($id);
        $formatBarcode = $user->format_barcode;
        return new ResponseResource(true, "format barcode", ['user_id' => $id, 'format_barcode' => $formatBarcode]);
    }

    public function allFormatBarcode(Request $request)
    {
        $query = $request->input('q');
        $users = User::whereNotNull('format_barcode')
            ->select('id', 'username', 'format_barcode');

        if ($query) {
            $users->where('username', 'LIKE', '%' . $query . '%')
                ->orWhere('format_barcode', 'LIKE', '%' . $query . '%');
        }

        $results = $users->paginate(33);

        return new ResponseResource(true, "list user berformat barcode", $results);
    }

}
