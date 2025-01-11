<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid black; padding: 5px; }
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