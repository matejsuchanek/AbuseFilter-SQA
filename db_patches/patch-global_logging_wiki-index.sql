-- Add abuse_filter_log index for afl_wiki.

CREATE INDEX afl_wiki_timestamp ON /*$wgDBprefix*/abuse_filter_log (afl_wiki, afl_timestamp);
