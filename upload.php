
<?php
$koneksi = new mysqli("localhost", "root", "", "db_barcode");

if (isset($_POST['upload'])) {
    $file = $_FILES['file']['tmp_name'];
    $handle = fopen($file, "r");

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $nomor = $data[0];
        $koneksi->query("INSERT INTO nomor_barcode (nomor) VALUES ('$nomor')");
    }

    echo "Upload berhasil! <a href='split.php'>Lanjut ke Split</a>";
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button type="submit" name="upload">Upload</button>
</form>
