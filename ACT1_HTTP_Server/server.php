<?php
// Configuration (Requirement 1: Listening on IP address and port)
$host = '127.0.0.1';
$port = 8080;
$null = NULL; // Helper variable for socket_select

// Error handling function
function error($msg) {
    echo "ERROR: $msg\n";
    exit(1);
}

// ------------------------------------------------
// 1. SOCKET CREATION AND BINDING
// ------------------------------------------------

// socket_create() - Create a TCP/IP socket stream
if (!($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))) {
    error("socket_create() failed: " . socket_strerror(socket_last_error()));
}

// Set socket options to reuse address (allows re-running server quickly)
if (!socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
    error("socket_set_option() failed: " . socket_strerror(socket_last_error()));
}

// socket_bind() - Bind the socket to the specified address and port
if (!socket_bind($socket, $host, $port)) {
    error("socket_bind() failed: " . socket_strerror(socket_last_error()));
}

// socket_listen() - Start listening for connections (Requirement 2 setup)
if (!socket_listen($socket, 5)) { // 5 is the backlog size
    error("socket_listen() failed: " . socket_strerror(socket_last_error()));
}

// Output confirmation
echo "Server listening on http://{$host}:{$port}...\n";

// Array of connected sockets (initially just the master listener)
$clients = array($socket);

// ------------------------------------------------
// 2. MAIN SERVER LOOP (Accepting and Responding)
// ------------------------------------------------

// Keep the server running indefinitely
while (true) {
    // Create a copy of the clients array for socket_select()
    $read = $clients;

    // socket_select() - Blocks until activity is detected on one of the sockets
    if (socket_select($read, $null, $null, 0, 10) === false) {
        error("socket_select() failed: " . socket_strerror(socket_last_error()));
    }

    // Loop through all sockets with activity
    if (in_array($socket, $read)) {
        // Activity on the main socket means a new client connection is pending
        
        // socket_accept() - Accept the new connection (Requirement 2: Accepting incoming connections)
        if (($client_socket = socket_accept($socket)) < 0) {
            error("socket_accept() failed: " . socket_strerror(socket_last_error()));
        } else {
            // Add the new client socket to the list
            $clients[] = $client_socket;
            echo "Client connected.\n";
        }
        
        // Remove the master socket from the active 'read' array for this loop iteration
        $key = array_search($socket, $read);
        unset($read[$key]);
    }

    // Process data from existing client sockets
    foreach ($read as $client_socket) {
        // socket_read() - Read the client's raw HTTP GET request (Requirement 3)
        $input = socket_read($client_socket, 2048); 
        
        // Check if the client closed the connection
        if ($input === false || trim($input) === '') {
            $key = array_search($client_socket, $clients);
            unset($clients[$key]);
            socket_close($client_socket);
            echo "Client disconnected.\n";
            continue;
        }

        // --- HTTP ROUTING AND RESPONSE GENERATION ---
        
        // Extract the request path (first line of the request)
        list($request_line) = explode("\n", $input, 2);
        list($method, $path) = explode(" ", $request_line, 3);
        
        // 4. Implementing basic routing (Requirement 4)
        if ($path === '/') {
            // 200 OK Response
            $status_line = "HTTP/1.1 200 OK\r\n";
            $response_body = "The \"Server Running Successfully!\" page.";
            
        } else {
            // 404 Not Found Response
            $status_line = "HTTP/1.1 404 Not Found\r\n";
            $response_body = "The \"404 Resource Not Found\" page.";
        }
        
        // Manually format the compliant HTTP response (Requirement 5 & HTTP Protocol)
        $response = $status_line;
        $response .= "Content-Type: text/plain\r\n";
        $response .= "Content-Length: " . strlen($response_body) . "\r\n";
        $response .= "Connection: close\r\n\r\n"; // Close connection after response
        $response .= $response_body;

        // Send the response back to the client
        socket_write($client_socket, $response, strlen($response));
        
        // Close the connection immediately after responding (standard for simple HTTP)
        socket_close($client_socket);
        $key = array_search($client_socket, $clients);
        unset($clients[$key]);
        echo "Response sent for path: {$path}\n";
    }
}
// Clean up the master socket when the script exits (though loop is infinite)
socket_close($socket);
?>