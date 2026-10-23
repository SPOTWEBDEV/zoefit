<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>


<button onclick="sendUssdRequest()">Click me</button>

<script>


const sendUssdRequest = async () => {
  // 1. Prepare form-encoded body payload matching your parameters
  const formData = new URLSearchParams();
  formData.append('username', 'Danielobinwa'); //
  formData.append('password', '!6sckl#SH'); //
  formData.append('apikey', '1152e1f3cf5b9390c5673645e2f1a5962ab7c59e2746eae321798dc2609a1fc4'); //
  formData.append('sim', '1'); //
  formData.append('server', 'DAMTNTWGK'); //[cite: 1]
  formData.append('number', '*312#'); //[cite: 1]
  formData.append('message', 'Hello from Thunder Client'); //[cite: 1]
  formData.append('url', 'http://localhost/zoofeeds/playground/'); // Response callback URL

  try {
    // 2. Make the POST request
    const response = await fetch('https://simhostng.com/api/ussd', { //[cite: 1]
      method: 'POST', //[cite: 1]
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: formData,
    });

    // 3. Parse and log the API response
    const result = await response.json();
    console.log('--- API Response ---');
    console.log(JSON.stringify(result, null, 2));
  } catch (error) {
    console.error('Error executing USSD request:', error);
  }
};

sendUssdRequest();


</script>
    
</body>
</html>