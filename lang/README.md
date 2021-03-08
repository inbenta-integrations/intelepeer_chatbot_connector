### STRUCTURE
The lang folder should contain a file for each language that the bot will speak. The name of the file should match the value specified to the bot in `/conf/current-conf-folder/conversation.php` at `default/lang` parameter. The values accepted are described in Chatbot API Routes `/conversation`

Here is an example of a lang file:
```php
    return array(
        'api_timeout' => 'Please, reformulate your question.',
        'no' => 'No',
        'thanks' => 'Thanks!',
        'yes' => 'Yes'
	);
```
