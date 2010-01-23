# Introduction

This is a simple [Hoptoad](http://hoptoadapp.com) notifier for PHP. It's been used in a few production sites now with success. It's not quite as fully featured as the official Ruby notifier but it works well.

# Thanks

Big thanks to Lou Kosak and Till Klampaeckel who have both contributed extensively to this project.

# Limitations

This notifier does not contain two big features from the Ruby notifier. The two are error filtering and deploy tracking. Error filtering will be coming in a future release.

For deploy tracking, since I use Capistrano to deploy my PHP apps, I simply use the Ruby notifier to perform the deploy tracking. For this reason, unless someone wants to contribute patches, I don't see deploy tracking coming to the php notifier.

# Requirements

To use the default _pear_ client install Pear's HTTP_Request2:

    pear install HTTP_Request2

To use the _curl_ client, install the PHP curl extension. To install on Ubuntu if you are using PHP 5 you would run:
		sudo apt-get install php5-curl
		sudo /etc/init.d/apache2 reload

# License

Copyright (c) 2010, Rich Cavanaugh
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
    * The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
