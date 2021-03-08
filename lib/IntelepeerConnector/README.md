### OBJECTIVE

This Intelepeer connector extends from the [Chatbot API Connector](https://github.com/inbenta-integrations/chatbot_api_connector) library. This library translates Intelepeer's messages into the Inbenta Chatbot API format and vice versa.

### FUNCTIONALITIES
This connector inherits the functionalities from the `ChatbotConnector` library. Currently, the features provided by this application are:

* Simple answers
* Multiple options
* Polar questions
* Chained answers
* Send information to webhook through forms

### HOW TO CUSTOMIZE

**Custom Behaviors**

If you need to customize the bot flow, you need to modify the class `IntelepeerConnector.php`. This class extends from the ChatbotConnector and here you can override all the parent methods.


### STRUCTURE

The `IntelepeerConnector` folder has some classes needed to use the ChatbotConnector with Intelepeer. These classes are used in the IntelepeerConnector constructor in order to provide the application with the components needed to send information to Intelepeer.


**External Digester folder**

This folder contains the class IntelepeerDigester. This class is a kind of "translator" between the Chatbot API and the json expected by Intelepeer. Mainly, the work performed by this class is to convert a message from the Chatbot API into a message accepted by the Intelepeer. It also does the inverse work, translating messages from Intelepeer into the format required by the Chatbot API.
