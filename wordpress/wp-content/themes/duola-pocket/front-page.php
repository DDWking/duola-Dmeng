<?php get_header(); ?>
<section class="code-desktop" aria-labelledby="desktop-guide-title">
    <div class="code-canvas" aria-hidden="true">
        <ol class="code-lines">
            <li><code><span class="code-comment">// pocket.config.js</span></code></li>
            <li><code>&nbsp;</code></li>
            <li><code><span class="code-keyword">const</span> pocket = {</code></li>
            <li><code>  owner: <span class="code-string">"哆啦D梦"</span>,</code></li>
            <li><code>  collections: [<span class="code-string">"photos"</span>, <span class="code-string">"words"</span>],</code></li>
            <li><code>  mood: <span class="code-string">"stay alive"</span>,</code></li>
            <li><code>};</code></li>
            <li><code>&nbsp;</code></li>
            <li><code><span class="code-keyword">while</span> (time.<span class="code-function">moves</span>()) {</code></li>
            <li><code>  pocket.<span class="code-function">save</span>(moment);</code></li>
            <li><code>}</code></li>
            <li><code>&nbsp;</code></li>
            <li><code><span class="code-comment">// build complete · memory preserved</span><span class="code-cursor">_</span></code></li>
        </ol>
    </div>
    <div class="desktop-guide">
        <h1 id="desktop-guide-title">某年某月某天</h1>
        <p>Stay alive!</p>
    </div>
</section>
<?php get_footer(); ?>
