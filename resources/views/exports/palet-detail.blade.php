<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page {
            size: A4 landscape; /* Mengatur halaman menjadi A4 dengan orientasi landscape */
            margin: 10mm; /* Memperkecil margin untuk memaksimalkan ruang konten */
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px; /* Menambahkan padding kecil untuk estetika */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px; /* Ukuran font tabel diperkecil untuk memuat lebih banyak data */
        }
        th, td {
            border: 1px solid black;
            padding: 4px; /* Padding lebih kecil untuk menghemat ruang */
            text-align: left;
        }
        th {
            background-color: #f2f2f2; /* Memberikan sedikit warna latar untuk header tabel */
        }
        h3 {
            margin-top: 10px; /* Jarak antar heading dikurangi */
            margin-bottom: 5px;
        }
        p {
            margin: 2px 0; /* Mengurangi margin pada paragraf */
        }
    </style>
</head>
<body>
    <h3>Palet Details</h3>
    @foreach($paletHeaders as $header)
        <p><strong>{{ ucwords(str_replace('_', ' ', $header)) }}:</strong> {{ $palet->$header }}</p>
    @endforeach
    
    <h3>Product List</h3>
    <table>
        <tr>
            @foreach($paletProductsHeaders as $header)
                <th>{{ ucwords(str_replace('_', ' ', $header)) }}</th>
            @endforeach
        </tr>
        @foreach($palet->paletProducts as $product)
            <tr>
                @foreach($paletProductsHeaders as $header)
                    <td>{{ $product->$header }}</td>
                @endforeach
            </tr>
        @endforeach
    </table>
</body>
</html>
