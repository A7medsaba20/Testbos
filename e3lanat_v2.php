<?php
// Telegram bot token
$token = '7031183513:AAHfviStwZdBdYeKnPC-QfFJV0pXEGb1mVo';
$admin_id = '5896443755'; // ضع معرف الإدمن هنا

// Load the specialties and their corresponding tests from a text file
$specialties = json_decode(file_get_contents('specialties.json'), true);

// Load the tests from a separate text file
$tests = json_decode(file_get_contents('tests.json'), true);

// Load the user information from a text file
$users = array();
if (file_exists('users.txt')) {
  $userLines = file('users.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($userLines as $line) {
    $fields = explode('%', $line);
    $users[$fields[0]] = array(
      'current_test' => $fields[1],
      'current_specialty' => $fields[2],
      'correct_answers' => (int) $fields[3],
      'incorrect_answers' => (int) $fields[4],
      'score' => (float) $fields[5]
    );
  }
}

// Function to handle incoming messages
function handleMessage($message) {
  global $specialties, $tests, $users, $token, $admin_id;

  // Get the user's message
  $text = $message['text'];
  $chatId = $message['chat']['id'];
  $userId = $message['from']['id'];

  // Check if the user wants to take a test
  if (strpos($text, '/start') === 0) {
    // Send a list of specialties to choose from
    $response = 'Choose a specialty:';
    foreach ($specialties as $specialty => $specialtyTests) {
      $response .= "\n/$specialty";
    }
    sendMessage($chatId, $response);
  } elseif (strpos($text, '/') === 0) {
    // Get the specialty and test
    $specialty = substr($text, 1);
    if (!isset($specialties[$specialty])) {
      sendMessage($chatId, "Specialty not found!");
      return;
    }
    $testName = $specialties[$specialty][0]; // default to the first test
    $test = $tests[$testName];

    // Send the test questions
    $response = "Test: $specialty\n";
    foreach ($test['questions'] as $question) {
      $response .= $question['question'] . "\n";
      if (!empty($question['attachments'])) {
        $response .= "Attachments: " . implode(', ', $question['attachments']) . "\n";
      }
    }
    sendMessage($chatId, $response);

    // Update the user's current test and specialty
    $users[$userId]['current_test'] = $testName;
    $users[$userId]['current_specialty'] = $specialty;
    saveUsers($users);
  } elseif (strpos($text, 'answer') === 0) {
    // Handle user's answer
    $answers = explode(' ', $text);
    $specialty = $users[$userId]['current_specialty'];
    $testName = $users[$userId]['current_test'];
    $test = $tests[$testName];
    $score = 0;
    foreach ($test['questions'] as $i => $question) {
      if (isset($answers[$i + 1]) && $answers[$i + 1] == $question['answer']) {
        $score++;
      }
    }
    $users[$userId]['correct_answers'] += $score;
    $users[$userId]['incorrect_answers'] += count($test['questions']) - $score;
    $users[$userId]['score'] = ($users[$userId]['correct_answers'] / ($users[$userId]['correct_answers'] + $users[$userId]['incorrect_answers'])) * 100;
    saveUsers($users);

    $response = "You scored " . number_format($users[$userId]['score'], 2) . "% on the " . $users[$userId]['current_test'] . " test in " . $users[$userId]['current_specialty'] . "!";
    sendMessage($chatId, $response);
  } elseif (strpos($text, 'admin') === 0 && $userId == $admin_id) {
    // Handle admin commands
    $commands = explode(' ', $text);
    if ($commands[1] == 'addspecialty') {
      addSpecialty($commands[2]);
      sendMessage($chatId, "Specialty added!");
    } elseif ($commands[1] == 'addtest') {
      addTest($commands[2], $commands[3]);
      sendMessage($chatId, "Test added!");
    } elseif ($commands[1] == 'addquestion') {
      addQuestion($commands[2], $commands[3], implode(' ', array_slice($commands, 4)));
      sendMessage($chatId, "Question added!");
    }
  } else {
    sendMessage($chatId, "Invalid command or you don't have permission to use this command.");
  }
}

// Function to send a message to the user
function sendMessage($chatId, $text) {
  global $token;
  $url = "https://api.telegram.org/bot$token/sendMessage";
  $data = array('chat_id' => $chatId, 'text' => $text);
  $options = array('http' => array('method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($data)));
  $context = stream_context_create($options);
  file_get_contents($url, false, $context);
}

// Function to add a new specialty
function addSpecialty($specialty) {
  global $specialties;
  $specialties[$specialty] = array();
  file_put_contents('specialties.json', json_encode($specialties));
}

// Function to add a new test
function addTest($specialty, $testName) {
  global $specialties, $tests;
  $specialties[$specialty][] = $testName;
  $tests[$testName] = array('questions' => array());
  file_put_contents('specialties.json', json_encode($specialties));
  file_put_contents('tests.json', json_encode($tests));
}

// Function to add a new question
function addQuestion($specialty, $testName, $question) {
  global $tests;
  $tests[$testName]['questions'][] = array('question' => $question, 'answer' => '', 'attachments' => array());
  file_put_contents('tests.json', json_encode($tests));
}

// Function to save user data
function saveUsers($users) {
  $userLines = array();
  foreach ($users as $id => $user) {
    $userLines[] = $id . '%' . $user['current_test'] . '%' . $user['current_specialty'] . '%' . $user['correct_answers'] . '%' . $user['incorrect_answers'] . '%' . $user['score'];
  }
  file_put_contents('users.txt', implode("\n", $userLines));
}

// Set up the webhook
$webhookUrl = 'https://example.com/bot.php'; // replace with your URL
$apiUrl = "https://api.telegram.org/bot$token/setWebhook";
$data = array('url' => $webhookUrl);
$options = array('http' => array('method' => 'POST', 'header' => 'Content-Type: application/json', 'content' => json_encode($data)));
$context = stream_context_create($options);
file_get_contents($apiUrl, false, $context);

// Handle incoming updates
$update = json_decode(file_get_contents('php://input'), true);
if (isset($update['message'])) {
  handleMessage($update['message']);
}

?>
