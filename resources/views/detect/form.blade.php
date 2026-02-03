<form method="post" action="/detect" enctype="multipart/form-data">
    @csrf
    <input type="file" name="image">
    <button type="submit">上传检测</button>
</form>
