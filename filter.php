<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Filter Subjects</title>
  <style>
    /* ... (existing styles) ... */
  </style>
</head>
<body>
  <div class="filter-box">
    <h2>Filter Subjects</h2>
    <form action="filter.php" method="POST">
      <label for="year">Select Year:</label>
      <select name="year" id="year" required>
        <option value="">--Select Year--</option>
        <option value="2023">2023</option>
        <option value="2024">2024</option>
        <option value="2025">2025</option>
        <option value="2026">2026</option>
      </select><br>

      <label for="subject">Select Subject:</label>
      <select name="subject" id="subject" required>
        <option value="">--Select Subject--</option>
        <option value="Mathematics">Mathematics</option>
        <option value="Science">Science</option>
        <option value="Programming">Programming</option>
        <option value="History">History</option>
      </select><br>

      <button type="submit">Filter</button>
    </form>
  </div>
</body>
</html>