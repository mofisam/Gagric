<!DOCTYPE html>
<html>
<head>
    <title>Domain Availability Checker</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        input { padding: 8px; width: 250px; }
        button { padding: 8px 12px; }
        #result { margin-top: 20px; }
    </style>
</head>
<body>

<h2>Domain Checker</h2>

<input type="text" id="domain" placeholder="example.com">
<button onclick="checkDomain()">Check</button>

<div id="result"></div>

<script>
async function checkDomain() {
    const domain = document.getElementById("domain").value;
    const result = document.getElementById("result");

    result.innerHTML = "Checking...";

    const response = await fetch("check.php?domain=" + domain);
    const data = await response.json();

    if (data.error) {
        result.innerHTML = "❌ " + data.error;
        return;
    }

    if (data.available) {
        result.innerHTML = "<b>✅ Domain is available!</b>";
    } else {
        result.innerHTML = `
            <b>❌ Domain is taken</b><br>
            Registrar: ${data.registrar}<br>
            Created: ${data.created}<br>
            Expires: ${data.expires}
        `;
    }
}
</script>

</body>
</html>
